<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Books\Ledger;
use App\Models\Engagement;

/**
 * Coaching's money into the ledger as it moves, from wherever it moves: a review, lesson or pack
 * is a sale once it's paid (its PaymentIntent arrives); any refund is the amount `refunded_cents`
 * grew by. A package's months are sales from Stripe's invoices (Engagements::packagePaid).
 */
final class EngagementLedger
{
    public function __construct(private readonly Ledger $ledger) {}

    public function updated(Engagement $engagement): void
    {
        if ($engagement->wasChanged('stripe_payment_intent') && $engagement->getOriginal('stripe_payment_intent') === null && $engagement->kind !== Engagement::PACKAGE) {
            $this->ledger->sale($engagement);
        }

        $before = (int) $engagement->getOriginal('refunded_cents');

        if ($engagement->wasChanged('refunded_cents') && $engagement->refunded_cents > $before) {
            $this->ledger->refund($engagement, $engagement->refunded_cents, $engagement->refunded_cents - $before);
        }
    }
}
