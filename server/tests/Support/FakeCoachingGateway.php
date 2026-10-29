<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Services\Coaching\CoachingGateway;
use RuntimeException;

/**
 * Stripe Connect as the coaching tests need it: everything asked is kept, nothing leaves.
 */
final class FakeCoachingGateway implements CoachingGateway
{
    public bool $ready = true;

    /** @var array{chargesEnabled: bool, payoutsEnabled: bool, detailsSubmitted: bool} */
    public array $account = ['chargesEnabled' => true, 'payoutsEnabled' => true, 'detailsSubmitted' => true];

    /** @var list<array<string, mixed>> */
    public array $checkouts = [];

    /** @var list<array{0: string, 1: int, 2: bool}> */
    public array $refunds = [];

    /** @var list<array<string, mixed>> */
    public array $transfers = [];

    /** @var list<array{0: string, 1: int}> */
    public array $reversals = [];

    /** @var list<string> */
    public array $cancelled = [];

    /** @var list<string> */
    public array $cancelledNow = [];

    /** @var list<string> */
    public array $expired = [];

    public string $paymentStatus = 'paid';

    public bool $charges = true;

    public bool $failRefunds = false;

    /** @var array<string, true> */
    private array $keys = [];

    private int $next = 0;

    public function ready(): bool
    {
        return $this->ready;
    }

    public function createAccount(User $coach, string $country): string
    {
        return 'acct_fake'.(++$this->next);
    }

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        return 'https://connect.stripe.test/setup/'.$accountId;
    }

    public function account(string $accountId): array
    {
        return $this->account;
    }

    public function dashboardUrl(string $accountId): string
    {
        return 'https://connect.stripe.test/express/'.$accountId;
    }

    public function paymentCheckout(User $payer, int $amountCents, string $currency, string $name, string $successUrl, string $cancelUrl, array $metadata, ?string $destination = null, int $feeCents = 0): array
    {
        $id = 'cs_fake'.(++$this->next);
        $this->checkouts[] = ['id' => $id, 'mode' => 'payment', 'amount' => $amountCents, 'currency' => $currency, 'metadata' => $metadata, 'destination' => $destination, 'fee' => $feeCents];

        return ['id' => $id, 'url' => 'https://checkout.stripe.test/'.$id];
    }

    public function subscriptionCheckout(User $payer, int $amountCents, string $currency, string $name, string $successUrl, string $cancelUrl, array $metadata, string $destination, int $feePercent): array
    {
        $id = 'cs_fake'.(++$this->next);
        $this->checkouts[] = ['id' => $id, 'mode' => 'subscription', 'amount' => $amountCents, 'currency' => $currency, 'metadata' => $metadata, 'destination' => $destination, 'feePercent' => $feePercent];

        return ['id' => $id, 'url' => 'https://checkout.stripe.test/'.$id];
    }

    public function checkoutResult(string $sessionId): array
    {
        $paid = ['status' => 'complete', 'paymentStatus' => $this->paymentStatus];

        foreach ($this->checkouts as $checkout) {
            if ($checkout['id'] === $sessionId) {
                return $checkout['mode'] === 'subscription'
                    ? [...$paid, 'paymentIntent' => null, 'charge' => null, 'subscription' => 'sub_'.$sessionId]
                    : [...$paid, 'paymentIntent' => 'pi_'.$sessionId, 'charge' => $this->charges ? 'ch_'.$sessionId : null, 'subscription' => null];
            }
        }

        return [...$paid, 'paymentIntent' => 'pi_'.$sessionId, 'charge' => 'ch_'.$sessionId, 'subscription' => null];
    }

    public function expireCheckout(string $sessionId): void
    {
        $this->expired[] = $sessionId;
    }

    public function refund(string $paymentIntent, int $amountCents, string $idempotencyKey, bool $reverseTransfer = false): void
    {
        if ($this->failRefunds) {
            throw new RuntimeException('Stripe said no (fake).');
        }

        // Stripe keeps what a key did: the same key again does nothing more.
        if (! isset($this->keys[$idempotencyKey])) {
            $this->keys[$idempotencyKey] = true;
            $this->refunds[] = [$paymentIntent, $amountCents, $reverseTransfer];
        }
    }

    public function chargeOf(string $paymentIntent): ?string
    {
        return 'ch_'.substr($paymentIntent, 3);
    }

    public function latestPackagePayment(string $subscriptionId): ?string
    {
        return 'pi_'.$subscriptionId;
    }

    public function transfer(int $amountCents, string $currency, string $destination, string $sourceCharge, string $group, string $idempotencyKey): string
    {
        if (! isset($this->keys[$idempotencyKey])) {
            $this->keys[$idempotencyKey] = true;
            $this->transfers[] = ['amount' => $amountCents, 'currency' => $currency, 'destination' => $destination, 'charge' => $sourceCharge, 'group' => $group];
        }

        return 'tr_'.md5($idempotencyKey);
    }

    public function reverseTransfer(string $transferId, int $amountCents, string $idempotencyKey): void
    {
        if (! isset($this->keys[$idempotencyKey])) {
            $this->keys[$idempotencyKey] = true;
            $this->reversals[] = [$transferId, $amountCents];
        }
    }

    public function cancelAtPeriodEnd(string $subscriptionId): void
    {
        $this->cancelled[] = $subscriptionId;
    }

    public function cancelPackageNow(string $subscriptionId): void
    {
        $this->cancelledNow[] = $subscriptionId;
    }
}
