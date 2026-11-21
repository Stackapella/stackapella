<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;
use Carbon\CarbonInterface;
use Laravel\Cashier\Subscription;

/**
 * What the server asks of Stripe, in one place: the real one (StripeGateway, through Cashier) and
 * the tests' fake never reach each other, and nothing else calls Stripe.
 */
interface BillingGateway
{
    /**
     * A Checkout session for Plus.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function checkout(User $user, string $price, string $currency, ?int $trialDays, bool $collectCard, string $successUrl, string $cancelUrl, array $metadata, string $terms, ?string $receiptNote = null): array;

    /**
     * Closes a Checkout that's still open, so it can't be paid; returns its status after: `expired`, or `complete`
     * when it was paid already.
     */
    public function closeCheckout(string $sessionId): string;

    /** Refunds what a subscription's latest paid invoice charged, in full. */
    public function refundSubscription(User $user, string $subscriptionId, string $idempotencyKey): void;

    /**
     * A Checkout session for a choir's seats (docs/choir-seats-plan.md): the "Choir seats" price, `$seats` of it, as the
     * subscription `$type` on the leader's account, its trial (if any) ending at `$trialEnd`.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function seatsCheckout(User $user, string $type, string $price, int $seats, string $currency, ?CarbonInterface $trialEnd, string $successUrl, string $cancelUrl, array $metadata, string $terms): array;

    /** A choir's seats made more or fewer (Stripe prorates), kept on the subscription's row too. */
    public function updateSeats(Subscription $subscription, int $seats): void;

    public function portalUrl(User $user, string $returnUrl): string;

    /**
     * The card on a subscription, as Stripe's fingerprint (never the number), or null.
     */
    public function cardFingerprint(string $subscriptionId): ?string;

    public function cancelNow(string $subscriptionId): void;

    /**
     * The customer a charge belongs to (a dispute names only its charge).
     */
    public function chargeCustomer(string $chargeId): ?string;

    /**
     * The account's latest paid Plus invoice: its PaymentIntent, what was paid and what's been
     * refunded of it already, or null when there's none.
     *
     * @return array{paymentIntent: string, cents: int, refunded: int, currency: string, paidAt: int}|null
     */
    public function latestPayment(User $user): ?array;

    /**
     * A refund of part or all of a payment, once per key.
     */
    public function refund(string $paymentIntent, int $cents, string $idempotencyKey): void;
}
