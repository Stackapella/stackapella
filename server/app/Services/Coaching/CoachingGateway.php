<?php

declare(strict_types=1);

namespace App\Services\Coaching;

use App\Models\User;

/**
 * What coaching asks of Stripe Connect, in one place: coaches' Express accounts, the singers'
 * Checkout, refunds and the coach's share (docs/coaching-marketplace-plan.md F3). The real one
 * (StripeCoachingGateway) and the tests' fake never reach each other.
 */
interface CoachingGateway
{
    /**
     * Stripe is set up here (its secret key): without it, coaching can't take money.
     */
    public function ready(): bool;

    public function createAccount(User $coach, string $country): string;

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string;

    /**
     * @return array{chargesEnabled: bool, payoutsEnabled: bool, detailsSubmitted: bool}
     */
    public function account(string $accountId): array;

    public function dashboardUrl(string $accountId): string;

    /**
     * A one-off payment on Stackapella's account; with a destination, the coach's share (the amount
     * less the fee) moves at once (a pack); without, it waits for transfer() (a review).
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function paymentCheckout(User $payer, int $amountCents, string $currency, string $name, string $successUrl, string $cancelUrl, array $metadata, ?string $destination = null, int $feeCents = 0): array;

    /**
     * A monthly subscription (a package): Stripe moves the coach's share each month, less the fee.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function subscriptionCheckout(User $payer, int $amountCents, string $currency, string $name, string $successUrl, string $cancelUrl, array $metadata, string $destination, int $feePercent): array;

    /**
     * What a Checkout made, and whether it's paid: its payment (and charge) or subscription.
     * `status` is open, complete or expired; `paymentStatus` paid, unpaid or no_payment_required.
     *
     * @return array{status: string, paymentStatus: string, paymentIntent: string|null, charge: string|null, subscription: string|null}
     */
    public function checkoutResult(string $sessionId): array;

    /**
     * Close a Checkout nobody should pay any more (a cancelled purchase).
     */
    public function expireCheckout(string $sessionId): void;

    /**
     * Every money call takes a key: a retry with the same key is the same call, never a second one.
     * A destination charge's refund takes the coach's share and the fee back with it.
     */
    public function refund(string $paymentIntent, int $amountCents, string $idempotencyKey, bool $reverseTransfer = false): void;

    /**
     * A PaymentIntent's charge, once it has one.
     */
    public function chargeOf(string $paymentIntent): ?string;

    /**
     * A package's last paid month's PaymentIntent (to refund it).
     */
    public function latestPackagePayment(string $subscriptionId): ?string;

    /**
     * The coach's share of a charge, moved to their account.
     */
    public function transfer(int $amountCents, string $currency, string $destination, string $sourceCharge, string $group, string $idempotencyKey): string;

    public function reverseTransfer(string $transferId, int $amountCents, string $idempotencyKey): void;

    /**
     * Stop a package at the end of the month it's in.
     */
    public function cancelAtPeriodEnd(string $subscriptionId): void;

    /**
     * Stop a package now (an account deleted, a coach gone).
     */
    public function cancelPackageNow(string $subscriptionId): void;
}
