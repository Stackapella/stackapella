<?php

declare(strict_types=1);

namespace App\Actions\Books;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinjaError;
use App\Support\Ids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Plus paid by invoice (docs/decisions.md, M10): Hillel invoices an account for months of Plus
 * from Stackapella's own Invoice Ninja, which emails it. An invoice counts as paid once Invoice
 * Ninja shows payments that completed (not pending, failed or refunded) covering it, read from
 * Invoice Ninja itself, never the webhook's word: on its "payment made" webhook (for every sent
 * invoice that payment touched), and hourly (a bank transfer that cleared later, a lost webhook).
 * Then the account has Plus for those months, from now or from when its current paid time ends.
 */
final class PlusInvoices
{
    public function __construct(private readonly Books $books, private readonly InvoiceNinja $ninja) {}

    public function create(User $admin, string $personSqid, int $cents, string $currency, int $months, ?string $ip): InvoiceNinjaRecord
    {
        $connection = Books::platformBooks();

        if ($connection === null || $connection->status !== InvoiceNinjaConnection::ACTIVE || $connection->webhook_id === null) {
            throw new Refusal(409, 'books_not_connected');
        }

        $person = User::query()->whereKey(Ids::decode($personSqid) ?? 0)->first() ?? throw new Refusal(404, 'not_found');

        if ($person->email === null) {
            throw new Refusal(409, 'email_required');
        }

        $record = $this->books->note($connection, InvoiceNinjaRecord::PLUS_INVOICE, 'plus-invoice:'.Str::lower(Str::random(12)), $cents, $currency, [
            'description' => __('books.line.plus_year', ['months' => $months]),
            'name' => $person->name,
            'email' => $person->email,
            'date' => now()->toDateString(),
            'months' => $months,
        ], $person->id);

        AuditLog::record('books.plus_invoice', $person, $admin, after: ['amountCents' => $cents, 'currency' => $currency, 'months' => $months], ip: $ip);

        return $record->refresh();
    }

    /**
     * Invoice Ninja's "payment made" webhook: every sent Plus invoice the payment touched is
     * checked. Answers how many became paid.
     */
    public function paid(InvoiceNinjaConnection $connection, string $paymentId): int
    {
        $payment = $this->ninja->payment($connection, $paymentId);

        if ($payment === null || $payment['invoiceIds'] === []) {
            return 0;
        }

        $sent = InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::PLUS_INVOICE)->where('status', InvoiceNinjaRecord::SENT)->whereIn('in_invoice_id', $payment['invoiceIds'])->get();

        return $sent->filter(fn (InvoiceNinjaRecord $record): bool => $this->settle($record))->count();
    }

    /**
     * Hourly: every sent Plus invoice is checked with Invoice Ninja.
     */
    public function sweep(): int
    {
        $connection = Books::platformBooks();

        if ($connection === null || $connection->status !== InvoiceNinjaConnection::ACTIVE) {
            return 0;
        }

        $paid = 0;

        foreach (InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::PLUS_INVOICE)->where('status', InvoiceNinjaRecord::SENT)->get() as $record) {
            try {
                $paid += $this->settle($record) ? 1 : 0;
            } catch (InvoiceNinjaError $e) {
                // The next hour tries again.
                report($e);
            }
        }

        return $paid;
    }

    /**
     * Paid once completed payments cover it: then Plus, once (the row goes `paid` under its lock).
     */
    private function settle(InvoiceNinjaRecord $record): bool
    {
        if ($record->in_invoice_id === null || $this->ninja->paidOn($record->loadMissing('connection')->connection, (string) $record->in_invoice_id) < $record->cents) {
            return false;
        }

        return DB::transaction(function () use ($record): bool {
            $locked = InvoiceNinjaRecord::query()->whereKey($record->id)->where('status', InvoiceNinjaRecord::SENT)->lockForUpdate()->first();
            $person = $locked?->user_id !== null ? User::query()->whereKey($locked->user_id)->lockForUpdate()->first() : null;

            if ($locked === null || $person === null) {
                return false;
            }

            $from = $person->plus_paid_until !== null && $person->plus_paid_until->isFuture() ? $person->plus_paid_until->copy() : now();
            $person->forceFill(['plus_paid_until' => $from->addMonths((int) ($locked->data['months'] ?? 12))])->save();
            $locked->forceFill(['status' => InvoiceNinjaRecord::PAID])->save();
            AuditLog::record('books.plus_invoice_paid', $person, null, after: ['until' => $person->plus_paid_until?->utc()->toIso8601ZuluString(), 'invoice' => $locked->in_invoice_id]);

            return true;
        });
    }
}
