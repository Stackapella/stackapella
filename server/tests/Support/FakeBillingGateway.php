<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Services\Billing\BillingGateway;

/**
 * Stripe, faked: nothing in the tests ever reaches it.
 */
final class FakeBillingGateway implements BillingGateway
{
    /** @var list<array<string, mixed>> */
    public array $checkouts = [];

    /** @var list<string> */
    public array $cancelled = [];

    public ?string $fingerprint = 'card-fingerprint-1';

    /** @var array<string, string> charge id to customer id */
    public array $charges = [];

    public function checkout(User $user, string $price, string $currency, ?int $trialDays, bool $collectCard, string $successUrl, string $cancelUrl, array $metadata, string $terms): array
    {
        $id = 'cs_test_'.(count($this->checkouts) + 1);
        $this->checkouts[] = compact('price', 'currency', 'trialDays', 'collectCard', 'successUrl', 'cancelUrl', 'metadata', 'terms') + ['id' => $id];
        $user->forceFill(['stripe_id' => $user->stripe_id ?? 'cus_test_'.$user->id])->save();

        return ['id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/'.$id];
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        return 'https://billing.stripe.com/p/session/test_'.$user->id;
    }

    public function cardFingerprint(string $subscriptionId): ?string
    {
        return $this->fingerprint;
    }

    public function cancelNow(string $subscriptionId): void
    {
        $this->cancelled[] = $subscriptionId;
    }

    public function chargeCustomer(string $chargeId): ?string
    {
        return $this->charges[$chargeId] ?? null;
    }

    /** @var array{paymentIntent: string, cents: int, refunded: int, currency: string, paidAt: int}|null */
    public ?array $latest = null;

    /** @var list<array{0: string, 1: int, 2: string}> */
    public array $refunds = [];

    public function latestPayment(User $user): ?array
    {
        return $this->latest;
    }

    public function refund(string $paymentIntent, int $cents, string $idempotencyKey): void
    {
        if (! in_array($idempotencyKey, array_column($this->refunds, 2), true)) {
            $this->refunds[] = [$paymentIntent, $cents, $idempotencyKey];

            // As Stripe does: the payment shows what's been refunded of it.
            if ($this->latest !== null && $this->latest['paymentIntent'] === $paymentIntent) {
                $this->latest['refunded'] += $cents;
            }
        }
    }
}
