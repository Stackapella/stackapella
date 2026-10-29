<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;

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
    public function checkout(User $user, string $price, string $currency, ?int $trialDays, bool $collectCard, string $successUrl, string $cancelUrl, array $metadata, string $terms): array;

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
