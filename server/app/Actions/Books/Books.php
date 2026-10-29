<?php

declare(strict_types=1);

namespace App\Actions\Books;

use App\Jobs\RecordInBooks;
use App\Models\CoachingPayment;
use App\Models\Engagement;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinjaError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Keeping the books in Invoice Ninja (docs/invoice-ninja-integration-plan.md): what to record is
 * noted as a row first (`invoice_ninja_records`, once per connection, kind and our reference), then
 * recorded by a queued job (RecordInBooks), so a sale never waits on Invoice Ninja. Recording is
 * idempotent: our reference goes in the invoice's `po_number` (or the expense's reference), the
 * row keeps Invoice Ninja's ids as they're made, and a retry looks for what an earlier try made
 * before making anything (an invoice, its payment, an expense, a refund).
 *
 * - A coach's books: each sale as an invoice to the singer with its payment; our commission as an
 *   expense, and given back (a negative expense) with each refund; each refund as a refund on the
 *   sale's payment, in order.
 * - Stackapella's books: each paid Plus invoice (its tax on a line of its own) and its refunds;
 *   each coach's month of commission, carrying over what earlier months left.
 */
final class Books
{
    /** A row left `recording` this long was cut off mid-way (a worker stopped): it's taken back. */
    public const STALE_MINUTES = 15;

    public function __construct(private readonly InvoiceNinja $ninja) {}

    public function noteSale(CoachingPayment $payment): void
    {
        $connection = self::coachBooks($payment->coach_id);
        $engagement = $payment->engagement;

        if ($connection === null || $engagement === null) {
            return;
        }

        $singer = $engagement->singer;
        $data = [
            'description' => $payment->description,
            'name' => $singer->name ?? __('coaching.deleted_account'),
            'email' => (string) ($singer->email ?? 'deleted-'.$engagement->id.'@users.stackapella.invalid'),
            'date' => $payment->paid_at->toDateString(),
            'charge' => $payment->stripe_charge,
        ];

        $this->note($connection, InvoiceNinjaRecord::SALE, $payment->ref, $payment->cents, $payment->currency, $data);

        if ($payment->fee_cents > 0) {
            $this->note($connection, InvoiceNinjaRecord::FEE, $payment->ref, $payment->fee_cents, $payment->currency, [...$data, 'description' => __('books.fee', ['description' => $payment->description])]);
        }
    }

    /**
     * A refund on the sale's payment, and our commission on it given back.
     */
    public function noteRefund(CoachingPayment $payment): void
    {
        $connection = self::coachBooks($payment->coach_id);

        if ($connection === null || $payment->sale_ref === null) {
            return;
        }

        $date = $payment->paid_at->toDateString();
        $this->note($connection, InvoiceNinjaRecord::REFUND, $payment->ref, -$payment->cents, $payment->currency, ['saleKind' => InvoiceNinjaRecord::SALE, 'saleRef' => $payment->sale_ref, 'date' => $date]);

        if ($payment->fee_cents < 0) {
            $this->note($connection, InvoiceNinjaRecord::FEE, $payment->ref, $payment->fee_cents, $payment->currency, ['description' => __('books.fee_back', ['description' => $payment->description]), 'date' => $date]);
        }
    }

    /**
     * A paid Plus invoice (Stripe's `invoice.paid`), in Stackapella's books: what was paid, its tax
     * on a line of its own, and the charge and PaymentIntent it was paid by (to match refunds).
     *
     * @param  array<string, mixed>  $invoice
     */
    public function plusPaid(User $user, array $invoice): void
    {
        $connection = self::platformBooks();
        $id = $invoice['id'] ?? null;
        $cents = (int) ($invoice['amount_paid'] ?? 0);

        if ($connection === null || ! is_string($id) || $cents <= 0) {
            return;
        }

        $paid = self::paidBy($invoice);
        $tax = min($cents, self::tax($invoice));
        $paidAt = $invoice['status_transitions']['paid_at'] ?? null;

        $this->note($connection, InvoiceNinjaRecord::PLUS, 'plus:'.$id, $cents, (string) ($invoice['currency'] ?? 'usd'), [
            'description' => __('books.line.plus'),
            'lines' => array_values(array_filter([
                ['description' => __('books.line.plus'), 'cents' => $cents - $tax],
                $tax > 0 ? ['description' => __('books.line.tax'), 'cents' => $tax] : null,
            ])),
            'name' => $user->name,
            'email' => (string) $user->email,
            'date' => (is_int($paidAt) ? Carbon::createFromTimestamp($paidAt) : now())->toDateString(),
            'charge' => $paid['charge'],
            'paymentIntent' => $paid['paymentIntent'],
        ], $user->id);
    }

    /**
     * A Plus charge refunded (Stripe's `charge.refunded`): `$total` is all that's refunded on it now.
     * Matched by the charge or its PaymentIntent (Stripe's newer invoices name only the latter).
     */
    public function plusRefunded(string $charge, ?string $paymentIntent, int $total): void
    {
        $connection = self::platformBooks();
        $sale = $connection !== null ? InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::PLUS)
            ->where(fn ($query) => $query->where('data->charge', $charge)->when($paymentIntent !== null, fn ($q) => $q->orWhere('data->paymentIntent', $paymentIntent)))->first() : null;

        if ($connection === null || $sale === null) {
            return;
        }

        $done = (int) InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::REFUND)->where('data->saleRef', $sale->ref)->sum('cents');

        if ($total > $done) {
            $this->note($connection, InvoiceNinjaRecord::REFUND, 'plus-refund:'.$charge.':'.$total, $total - $done, $sale->currency, ['saleKind' => InvoiceNinjaRecord::PLUS, 'saleRef' => $sale->ref, 'date' => now()->toDateString()]);
        }
    }

    /**
     * On the 1st: each coach's commission, one invoice per coach and currency, marked paid (we kept
     * it from each sale). It lists last month's sales and refunds, and carries over whatever earlier
     * months left (a month that came out negative is taken off the next). Backfilled history isn't
     * counted: it was never ours to invoice again.
     */
    public function commission(Carbon $month): int
    {
        $connection = self::platformBooks();

        if ($connection === null) {
            return 0;
        }

        $from = $month->copy()->startOfMonth();
        $until = $from->copy()->endOfMonth();
        $noted = 0;
        $keys = CoachingPayment::query()->whereNotNull('coach_id')->where('backfilled', false)->where('paid_at', '<=', $until)->select(['coach_id', 'currency'])->distinct()->get();

        foreach ($keys as $key) {
            $all = CoachingPayment::query()->where('coach_id', $key->coach_id)->where('currency', $key->currency)->where('backfilled', false)->where('paid_at', '<=', $until);
            $owed = (int) (clone $all)->sum('fee_cents');
            $invoiced = (int) InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::COMMISSION)->where('user_id', $key->coach_id)->where('currency', $key->currency)->where('ref', '!=', self::commissionRef((int) $key->coach_id, (string) $key->currency, $from))->sum('cents');
            $due = $owed - $invoiced;
            $coach = User::query()->find($key->coach_id);

            if ($due <= 0 || $coach === null) {
                continue;
            }

            $rows = (clone $all)->where('paid_at', '>=', $from)->orderBy('paid_at')->orderBy('id')->get();
            $lines = $rows->map(fn (CoachingPayment $p): array => ['description' => __('books.line.commission_sale', ['description' => $p->description, 'amount' => number_format($p->cents / 100, 2).' '.strtoupper($p->currency)]), 'cents' => $p->fee_cents])->values()->all();
            $carried = $due - (int) $rows->sum('fee_cents');

            if ($carried !== 0) {
                $lines[] = ['description' => __('books.line.commission_carried'), 'cents' => $carried];
            }

            $this->note($connection, InvoiceNinjaRecord::COMMISSION, self::commissionRef((int) $key->coach_id, (string) $key->currency, $from), $due, $key->currency, [
                'description' => __('books.line.commission', ['month' => $from->format('F Y')]),
                'lines' => $lines,
                'name' => $coach->name,
                'email' => (string) $coach->email,
                'date' => $until->toDateString(),
                'charge' => null,
            ], $coach->id);
            $noted++;
        }

        return $noted;
    }

    /**
     * A coach's last 12 months, once, when they first connect.
     */
    public function backfill(InvoiceNinjaConnection $connection, Ledger $ledger): int
    {
        if ($connection->user_id === null || $connection->backfilled_at !== null) {
            return 0;
        }

        $engagements = Engagement::query()->with(['singer', 'offering'])->where('coach_id', $connection->user_id)->whereNotNull('stripe_payment_intent')->where('created_at', '>', now()->subYear())->get();

        foreach ($engagements as $engagement) {
            $ledger->fill($engagement);
        }

        $payments = CoachingPayment::query()->with('engagement.singer')->where('coach_id', $connection->user_id)->where('paid_at', '>', now()->subYear())->orderBy('paid_at')->orderBy('id')->get();

        foreach ($payments as $payment) {
            $payment->kind === CoachingPayment::SALE ? $this->noteSale($payment) : $this->noteRefund($payment);
        }

        $connection->forceFill(['backfilled_at' => now()])->save();

        return $payments->count();
    }

    /**
     * Record one row in Invoice Ninja (the job's work), carrying on from what an earlier try made.
     * A refused token pauses the connection; anything else is kept with its reason and thrown, so
     * the job tries again. A row a stopped worker left `recording` is taken back after a while.
     */
    public function record(InvoiceNinjaRecord $record): void
    {
        $claimed = InvoiceNinjaRecord::query()->whereKey($record->id)
            ->where(fn ($query) => $query->whereIn('status', [InvoiceNinjaRecord::PENDING, InvoiceNinjaRecord::FAILED])
                ->orWhere(fn ($q) => $q->where('status', InvoiceNinjaRecord::RECORDING)->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES))))
            ->update(['status' => InvoiceNinjaRecord::RECORDING, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $record->refresh()->load('connection');
        $connection = $record->connection;

        if ($connection->status !== InvoiceNinjaConnection::ACTIVE) {
            $record->forceFill(['status' => InvoiceNinjaRecord::FAILED, 'error' => 'paused'])->save();

            return;
        }

        try {
            $done = match ($record->kind) {
                InvoiceNinjaRecord::FEE => $this->expense($connection, $record),
                InvoiceNinjaRecord::REFUND => $this->refund($connection, $record),
                default => $this->invoice($connection, $record),
            };
        } catch (InvoiceNinjaError $e) {
            $record->forceFill(['status' => InvoiceNinjaRecord::FAILED, 'error' => $e->reason])->save();

            if ($e->reason === InvoiceNinjaError::BAD_TOKEN) {
                $connection->forceFill(['status' => InvoiceNinjaConnection::NEEDS_RECONNECT, 'last_error' => $e->reason])->save();

                return;
            }

            throw $e;
        } catch (Throwable $e) {
            $record->forceFill(['status' => InvoiceNinjaRecord::FAILED, 'error' => InvoiceNinjaError::UNREACHABLE])->save();

            throw $e;
        }

        $record->forceFill($done
            ? ['status' => $record->kind === InvoiceNinjaRecord::PLUS_INVOICE ? InvoiceNinjaRecord::SENT : InvoiceNinjaRecord::RECORDED, 'error' => null, 'recorded_at' => now()]
            : ['status' => InvoiceNinjaRecord::FAILED, 'error' => 'waiting'])->save();

        // A sale or a refund now in the books lets the refunds waiting on it go.
        if ($done && in_array($record->kind, [InvoiceNinjaRecord::SALE, InvoiceNinjaRecord::PLUS, InvoiceNinjaRecord::REFUND], true)) {
            $saleRef = $record->kind === InvoiceNinjaRecord::REFUND ? (string) ($record->data['saleRef'] ?? '') : $record->ref;

            foreach (InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::REFUND)->where('data->saleRef', $saleRef)->where('status', InvoiceNinjaRecord::FAILED)->where('error', 'waiting')->orderBy('id')->get() as $waiting) {
                self::retry($waiting);
            }
        }
    }

    /**
     * Every hour: rows a stopped worker left `recording`, and rows that failed for a reason that
     * passes (Invoice Ninja out of reach, waiting for their sale), are tried again, up to 8 times.
     */
    public static function sweep(): int
    {
        $ids = InvoiceNinjaRecord::query()->whereHas('connection', fn ($query) => $query->where('status', InvoiceNinjaConnection::ACTIVE))->where('attempts', '<', 8)
            ->where(fn ($query) => $query->where(fn ($q) => $q->where('status', InvoiceNinjaRecord::RECORDING)->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES)))
                ->orWhere(fn ($q) => $q->where('status', InvoiceNinjaRecord::FAILED)->whereIn('error', [InvoiceNinjaError::UNREACHABLE, InvoiceNinjaError::TLS, 'waiting'])->where('updated_at', '<', now()->subMinutes(30))))
            ->pluck('id');

        foreach ($ids as $id) {
            RecordInBooks::dispatch((int) $id);
        }

        return $ids->count();
    }

    /**
     * Try again: one row, or every one a connection has waiting (after reconnecting).
     */
    public static function retry(InvoiceNinjaRecord $record): void
    {
        $record->forceFill(['status' => InvoiceNinjaRecord::PENDING, 'error' => null])->save();
        RecordInBooks::dispatch($record->id);
    }

    public static function retryAll(InvoiceNinjaConnection $connection): int
    {
        $ids = InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->whereIn('status', [InvoiceNinjaRecord::FAILED, InvoiceNinjaRecord::PENDING])->pluck('id');

        foreach ($ids as $id) {
            InvoiceNinjaRecord::query()->whereKey($id)->update(['status' => InvoiceNinjaRecord::PENDING, 'error' => null, 'updated_at' => now()]);
            RecordInBooks::dispatch((int) $id);
        }

        return $ids->count();
    }

    public static function coachBooks(?int $coachId): ?InvoiceNinjaConnection
    {
        return $coachId !== null ? InvoiceNinjaConnection::query()->where('owner_key', 'user:'.$coachId)->first() : null;
    }

    public static function platformBooks(): ?InvoiceNinjaConnection
    {
        return InvoiceNinjaConnection::query()->where('owner_key', InvoiceNinjaConnection::PLATFORM)->first();
    }

    /**
     * What a Stripe invoice was paid by: its charge (older API versions) or its payment's
     * PaymentIntent and charge (newer ones, under `payments`).
     *
     * @param  array<string, mixed>  $invoice
     * @return array{charge: string|null, paymentIntent: string|null}
     */
    public static function paidBy(array $invoice): array
    {
        $payment = (array) ($invoice['payments']['data'][0]['payment'] ?? []);
        $charge = $invoice['charge'] ?? $payment['charge'] ?? null;
        $intent = $invoice['payment_intent'] ?? $payment['payment_intent'] ?? null;

        return ['charge' => is_string($charge) ? $charge : null, 'paymentIntent' => is_string($intent) ? $intent : null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function note(InvoiceNinjaConnection $connection, string $kind, string $ref, int $cents, string $currency, array $data, ?int $userId = null): InvoiceNinjaRecord
    {
        $record = InvoiceNinjaRecord::query()->firstOrCreate(['connection_id' => $connection->id, 'kind' => $kind, 'ref' => mb_substr($ref, 0, 120)], [
            'status' => InvoiceNinjaRecord::PENDING,
            'user_id' => $userId,
            'cents' => $cents,
            'currency' => strtolower($currency),
            'data' => $data,
        ]);

        if ($record->wasRecentlyCreated) {
            RecordInBooks::dispatch($record->id)->afterCommit();
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private static function tax(array $invoice): int
    {
        if (is_array($invoice['total_taxes'] ?? null)) {
            return (int) array_sum(array_map(static fn (mixed $t): int => is_array($t) ? (int) ($t['amount'] ?? 0) : 0, $invoice['total_taxes']));
        }

        return (int) ($invoice['tax'] ?? 0);
    }

    private static function commissionRef(int $coachId, string $currency, Carbon $month): string
    {
        return 'commission:'.$coachId.':'.$currency.':'.$month->format('Y-m');
    }

    /**
     * An invoice (with its client) and, unless it waits to be paid, its payment. Anything not yet
     * known is looked for first (by our reference), so a lost answer is never made twice.
     */
    private function invoice(InvoiceNinjaConnection $connection, InvoiceNinjaRecord $record): bool
    {
        $data = $record->data;
        $paying = $record->kind !== InvoiceNinjaRecord::PLUS_INVOICE;

        if ($record->in_client_id === null) {
            $email = (string) ($data['email'] ?? '');
            $client = $this->ninja->findClient($connection, $email, $record->currency) ?? $this->ninja->createClient($connection, (string) ($data['name'] ?? $email), $email, $record->currency);
            $record->forceFill(['in_client_id' => $client])->save();
        }

        if ($record->in_invoice_id === null || ($paying && $record->in_payment_id === null)) {
            $found = $this->ninja->findInvoice($connection, $record->ref);

            if ($found !== null) {
                $record->forceFill(['in_invoice_id' => $found['id'], 'in_payment_id' => $record->in_payment_id ?? $found['paymentId']])->save();
            }
        }

        if ($record->in_invoice_id === null) {
            $lines = is_array($data['lines'] ?? null) ? array_values($data['lines']) : [['description' => (string) ($data['description'] ?? ''), 'cents' => $record->cents]];
            $email = match ($record->kind) {
                InvoiceNinjaRecord::PLUS_INVOICE => true,
                InvoiceNinjaRecord::SALE => $connection->send_email,
                default => false,
            };
            $invoice = $this->ninja->createInvoice($connection, (string) $record->in_client_id, $lines, $record->ref, (string) ($data['date'] ?? now()->toDateString()), $email);
            $record->forceFill(['in_invoice_id' => $invoice])->save();
        }

        if ($paying && $record->in_payment_id === null) {
            $payment = $this->ninja->createPayment($connection, (string) $record->in_client_id, (string) $record->in_invoice_id, $record->cents, (string) ($data['date'] ?? now()->toDateString()), (string) ($data['charge'] ?? $record->ref));
            $record->forceFill(['in_payment_id' => $payment])->save();
        }

        return true;
    }

    /**
     * Our commission as the coach's expense (negative when given back with a refund).
     */
    private function expense(InvoiceNinjaConnection $connection, InvoiceNinjaRecord $record): bool
    {
        if ($record->in_expense_id === null) {
            $reference = 'fee:'.$record->ref;
            $expense = $this->ninja->findExpense($connection, $reference)
                ?? $this->ninja->createExpense($connection, $this->ninja->vendor($connection, __('books.vendor')), $record->cents, $record->currency, (string) ($record->data['date'] ?? now()->toDateString()), (string) ($record->data['description'] ?? ''), $reference);
            $record->forceFill(['in_expense_id' => $expense])->save();
        }

        return true;
    }

    /**
     * A refund on the sale's payment, in order (each waits for the ones before it), once: Invoice
     * Ninja already showing this one's total refunded means an earlier try got through.
     */
    private function refund(InvoiceNinjaConnection $connection, InvoiceNinjaRecord $record): bool
    {
        $saleRef = (string) ($record->data['saleRef'] ?? '');
        $sale = InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', (string) ($record->data['saleKind'] ?? InvoiceNinjaRecord::SALE))->where('ref', $saleRef)->first();

        if ($sale === null || $sale->status !== InvoiceNinjaRecord::RECORDED || $sale->in_payment_id === null || $sale->in_invoice_id === null) {
            return false;
        }

        $earlier = InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::REFUND)->where('data->saleRef', $saleRef)->where('id', '<', $record->id);

        if ((clone $earlier)->where('status', '!=', InvoiceNinjaRecord::RECORDED)->exists()) {
            return false;
        }

        $target = (int) $earlier->sum('cents') + $record->cents;
        $refunded = $this->ninja->refundedOn($connection, $sale->in_payment_id);

        if ($refunded < $target) {
            $this->ninja->refund($connection, $sale->in_payment_id, $sale->in_invoice_id, min($record->cents, $target - $refunded), (string) ($record->data['date'] ?? now()->toDateString()));
        }

        $record->forceFill(['in_payment_id' => $sale->in_payment_id, 'in_invoice_id' => $sale->in_invoice_id])->save();

        return true;
    }
}
