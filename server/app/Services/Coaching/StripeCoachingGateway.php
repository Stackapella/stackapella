<?php

declare(strict_types=1);

namespace App\Services\Coaching;

use App\Models\User;
use Laravel\Cashier\Cashier;

/**
 * Stripe Connect for coaching (Express accounts, after Event Schedule's StripeController), through
 * Cashier's Stripe client.
 */
final class StripeCoachingGateway implements CoachingGateway
{
    public function ready(): bool
    {
        return (string) config('cashier.secret') !== '';
    }

    public function createAccount(User $coach, string $country): string
    {
        return (string) Cashier::stripe()->accounts->create([
            'type' => 'express',
            'country' => $country,
            'email' => (string) $coach->email,
            'capabilities' => ['card_payments' => ['requested' => true], 'transfers' => ['requested' => true]],
            'metadata' => ['user' => $coach->sqid()],
        ])->id;
    }

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        return (string) Cashier::stripe()->accountLinks->create(['account' => $accountId, 'refresh_url' => $refreshUrl, 'return_url' => $returnUrl, 'type' => 'account_onboarding'])->url;
    }

    public function account(string $accountId): array
    {
        $account = Cashier::stripe()->accounts->retrieve($accountId);

        return ['chargesEnabled' => (bool) $account->charges_enabled, 'payoutsEnabled' => (bool) $account->payouts_enabled, 'detailsSubmitted' => (bool) $account->details_submitted];
    }

    public function dashboardUrl(string $accountId): string
    {
        return (string) Cashier::stripe()->accounts->createLoginLink($accountId)->url;
    }

    public function paymentCheckout(User $payer, int $amountCents, string $currency, string $name, string $successUrl, string $cancelUrl, array $metadata, ?string $destination = null, int $feeCents = 0): array
    {
        $session = Cashier::stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $payer->createOrGetStripeCustomer()->id,
            // Cards only: a delayed method (a bank debit) would complete the Checkout unpaid.
            'payment_method_types' => ['card'],
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => $currency, 'unit_amount' => $amountCents, 'product_data' => ['name' => $name]]]],
            'payment_intent_data' => array_filter([
                'metadata' => $metadata,
                'transfer_group' => $metadata['engagement'] ?? null,
                'application_fee_amount' => $destination !== null ? $feeCents : null,
                'transfer_data' => $destination !== null ? ['destination' => $destination] : null,
            ], static fn (mixed $value): bool => $value !== null),
            'metadata' => $metadata,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    public function subscriptionCheckout(User $payer, int $amountCents, string $currency, string $name, string $successUrl, string $cancelUrl, array $metadata, string $destination, int $feePercent): array
    {
        $session = Cashier::stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $payer->createOrGetStripeCustomer()->id,
            'payment_method_types' => ['card'],
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => $currency, 'unit_amount' => $amountCents, 'recurring' => ['interval' => 'month'], 'product_data' => ['name' => $name]]]],
            // `type` keeps Cashier from taking it for Plus (its subscriptions default to "default").
            'subscription_data' => ['metadata' => [...$metadata, 'type' => 'coaching'], 'application_fee_percent' => $feePercent, 'transfer_data' => ['destination' => $destination]],
            'metadata' => $metadata,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    public function checkoutResult(string $sessionId): array
    {
        $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent']]);
        $intent = $session->payment_intent;

        return [
            'status' => (string) $session->status,
            'paymentStatus' => (string) $session->payment_status,
            'paymentIntent' => is_object($intent) ? (string) $intent->id : (is_string($intent) ? $intent : null),
            'charge' => is_object($intent) && is_string($intent->latest_charge ?? null) ? $intent->latest_charge : null,
            'subscription' => is_string($session->subscription ?? null) ? $session->subscription : null,
        ];
    }

    public function expireCheckout(string $sessionId): void
    {
        $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

        if ($session->status === 'open') {
            Cashier::stripe()->checkout->sessions->expire($sessionId);
        }
    }

    public function refund(string $paymentIntent, int $amountCents, string $idempotencyKey, bool $reverseTransfer = false): void
    {
        Cashier::stripe()->refunds->create(array_filter([
            'payment_intent' => $paymentIntent,
            'amount' => $amountCents,
            'reverse_transfer' => $reverseTransfer ?: null,
            'refund_application_fee' => $reverseTransfer ?: null,
        ], static fn (mixed $value): bool => $value !== null), ['idempotency_key' => $idempotencyKey]);
    }

    public function chargeOf(string $paymentIntent): ?string
    {
        $charge = Cashier::stripe()->paymentIntents->retrieve($paymentIntent)->latest_charge;

        return is_string($charge) ? $charge : null;
    }

    public function latestPackagePayment(string $subscriptionId): ?string
    {
        $invoices = Cashier::stripe()->invoices->all(['subscription' => $subscriptionId, 'status' => 'paid', 'limit' => 1, 'expand' => ['data.payments']]);
        $invoice = $invoices->data[0] ?? null;
        $payment = $invoice?->payments->data[0]->payment->payment_intent ?? null;

        return is_string($payment) ? $payment : null;
    }

    public function transfer(int $amountCents, string $currency, string $destination, string $sourceCharge, string $group, string $idempotencyKey): string
    {
        return (string) Cashier::stripe()->transfers->create(['amount' => $amountCents, 'currency' => $currency, 'destination' => $destination, 'source_transaction' => $sourceCharge, 'transfer_group' => $group], ['idempotency_key' => $idempotencyKey])->id;
    }

    public function reverseTransfer(string $transferId, int $amountCents, string $idempotencyKey): void
    {
        Cashier::stripe()->transfers->createReversal($transferId, ['amount' => $amountCents], ['idempotency_key' => $idempotencyKey]);
    }

    public function cancelAtPeriodEnd(string $subscriptionId): void
    {
        Cashier::stripe()->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);
    }

    public function cancelPackageNow(string $subscriptionId): void
    {
        Cashier::stripe()->subscriptions->cancel($subscriptionId);
    }
}
