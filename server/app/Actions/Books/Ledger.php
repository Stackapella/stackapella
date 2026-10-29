<?php

declare(strict_types=1);

namespace App\Actions\Books;

use App\Actions\Coaching\Engagements;
use App\Models\CoachingPayment;
use App\Models\Engagement;
use App\Models\ExercisePack;
use App\Models\LessonBooking;
use Illuminate\Support\Carbon;

/**
 * Coaching's money as it moved, once each (docs/invoice-ninja-integration-plan.md): a sale when a
 * review, lesson or pack is paid, or a package's month is; a refund for each amount given back,
 * against the latest sale. Engagements call it as they change (EngagementLedger); the coach's
 * books and our monthly commission are made from it.
 */
final class Ledger
{
    public function __construct(private readonly Books $books) {}

    /**
     * A sale: `$period` names a package's month (its period end); the rest are one sale each.
     */
    public function sale(Engagement $engagement, ?string $period = null, ?Carbon $at = null, ?int $cents = null, ?string $charge = null, bool $backfilled = false): ?CoachingPayment
    {
        $cents ??= $engagement->price_cents;

        if ($cents <= 0 || $engagement->kind === Engagement::TEACHER) {
            return null;
        }

        $payment = CoachingPayment::query()->firstOrCreate(['ref' => 'sale:'.$engagement->id.($period !== null ? ':'.$period : '')], [
            'engagement_id' => $engagement->id,
            'coach_id' => $engagement->coach_id,
            'kind' => CoachingPayment::SALE,
            'cents' => $cents,
            'fee_cents' => $cents === $engagement->price_cents ? $engagement->fee_cents : Engagements::fee($cents),
            'currency' => (string) ($engagement->currency ?? 'usd'),
            'description' => mb_substr(self::describe($engagement, $period), 0, 250),
            'stripe_charge' => $charge ?? $engagement->stripe_charge,
            'backfilled' => $backfilled,
            'paid_at' => $at ?? now(),
        ]);

        if ($payment->wasRecentlyCreated) {
            $this->books->noteSale($payment);

            // Money given back before its sale was known (a package's first month, refunded on its
            // Checkout before Stripe's invoice came): recorded now, against it.
            $given = -(int) CoachingPayment::query()->where('engagement_id', $engagement->id)->where('kind', CoachingPayment::REFUND)->sum('cents');

            if (! $backfilled && $engagement->refunded_cents > $given) {
                $this->refund($engagement, $engagement->refunded_cents, $engagement->refunded_cents - $given);
            }
        }

        return $payment;
    }

    /**
     * A package's month, from Stripe's paid invoice (`invoice.paid`): found by the engagement in
     * the subscription's metadata, so even the first month (whose invoice can come before its
     * Checkout's event) is recorded, once per invoice.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function packageMonth(array $invoice): ?CoachingPayment
    {
        $metadata = array_filter([
            $invoice['subscription_details']['metadata'] ?? null,
            $invoice['parent']['subscription_details']['metadata'] ?? null,
            $invoice['lines']['data'][0]['metadata'] ?? null,
        ], 'is_array');
        $sqid = collect($metadata)->map(fn (array $m): mixed => $m['engagement'] ?? null)->first(fn (mixed $s): bool => is_string($s) && $s !== '');
        $engagement = is_string($sqid) ? Engagement::query()->with(['singer', 'offering'])->whereSqid($sqid)->first() : null;
        $id = $invoice['id'] ?? null;
        $paid = (int) ($invoice['amount_paid'] ?? 0);

        if ($engagement === null || $engagement->kind !== Engagement::PACKAGE || ! is_string($id) || $paid <= 0) {
            return null;
        }

        // When it was paid (a renewal paid late after a retry belongs to the month it was paid in).
        $paidAt = $invoice['status_transitions']['paid_at'] ?? null;

        return $this->sale($engagement, $id, is_int($paidAt) ? Carbon::createFromTimestamp($paidAt) : now(), $paid, Books::paidBy($invoice)['charge']);
    }

    /**
     * Money given back: `$total` is all that's been refunded on the engagement now (so each step is
     * recorded once), `$amount` this step.
     */
    public function refund(Engagement $engagement, int $total, int $amount, ?Carbon $at = null, bool $backfilled = false): ?CoachingPayment
    {
        $sale = CoachingPayment::query()->where('engagement_id', $engagement->id)->where('kind', CoachingPayment::SALE)->orderByDesc('paid_at')->orderByDesc('id')->first();

        if ($sale === null || $amount <= 0) {
            return null;
        }

        $payment = CoachingPayment::query()->firstOrCreate(['ref' => 'refund:'.$engagement->id.':'.$total], [
            'engagement_id' => $engagement->id,
            'coach_id' => $engagement->coach_id,
            'kind' => CoachingPayment::REFUND,
            'sale_ref' => $sale->ref,
            'cents' => -$amount,
            'fee_cents' => -intdiv($amount * $sale->fee_cents, max(1, $sale->cents)),
            'currency' => $sale->currency,
            'description' => $sale->description,
            'stripe_charge' => $sale->stripe_charge,
            'backfilled' => $backfilled,
            'paid_at' => $at ?? now(),
        ]);

        if ($payment->wasRecentlyCreated) {
            $this->books->noteRefund($payment);
        }

        return $payment;
    }

    /**
     * An engagement paid before the ledger existed (a coach's backfill): its sale and what's been
     * refunded, as at the time. A package's past months aren't known, so packages aren't filled.
     */
    public function fill(Engagement $engagement): void
    {
        if ($engagement->kind === Engagement::PACKAGE || $engagement->stripe_payment_intent === null || CoachingPayment::query()->where('engagement_id', $engagement->id)->exists()) {
            return;
        }

        $this->sale($engagement, at: $engagement->created_at, backfilled: true);

        if ($engagement->refunded_cents > 0) {
            $this->refund($engagement, $engagement->refunded_cents, $engagement->refunded_cents, $engagement->ended_at ?? $engagement->updated_at, backfilled: true);
        }
    }

    private static function describe(Engagement $engagement, ?string $period): string
    {
        $singer = $engagement->singer->name ?? __('coaching.deleted_account');
        $title = match ($engagement->kind) {
            Engagement::PACK => (string) (ExercisePack::query()->whereKey($engagement->pack_id)->value('approved_title') ?? ExercisePack::query()->whereKey($engagement->pack_id)->value('title') ?? ''),
            default => (string) ($engagement->offering->title ?? ''),
        };

        return match ($engagement->kind) {
            Engagement::LESSON => __('books.line.lesson', [
                'title' => $title,
                'singer' => $singer,
                'date' => ($lesson = LessonBooking::query()->where('engagement_id', $engagement->id)->first()) !== null ? $lesson->starts_at->setTimezone($lesson->timezone)->format('j M Y, H:i') : '',
                'minutes' => (string) ($lesson !== null ? $lesson->starts_at->diffInMinutes($lesson->ends_at) : ''),
            ]),
            Engagement::PACKAGE => __('books.line.package', ['title' => $title, 'singer' => $singer, 'month' => now()->format('F Y')]),
            Engagement::PACK => __('books.line.pack', ['title' => $title, 'singer' => $singer]),
            default => __('books.line.review', ['title' => $title, 'singer' => $singer]),
        };
    }
}
