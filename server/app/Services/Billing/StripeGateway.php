<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;
use Laravel\Cashier\Cashier;

/**
 * Stripe, through Cashier (docs/backend-plan.md D8, after Event Schedule's SubscriptionController).
 */
final class StripeGateway implements BillingGateway
{
    public function checkout(User $user, string $price, string $currency, ?int $trialDays, bool $collectCard, string $successUrl, string $cancelUrl, array $metadata, string $terms): array
    {
        $builder = $user->newSubscription('default', $price);

        if ($trialDays !== null) {
            $builder->trialDays($trialDays);
        }

        $checkout = $builder->checkout(array_filter([
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'currency' => $currency,
            'metadata' => $metadata,
            // The no-card trial (trust level 2) takes the card only when it's time to pay, and
            // ends (rather than running on unpaid) if no card arrives by then.
            'payment_method_collection' => $collectCard ? 'always' : 'if_required',
            'subscription_data' => $collectCard ? null : ['trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']]],
            // That it renews, at what, and how to cancel (and the trial's end), above the button.
            'custom_text' => ['submit' => ['message' => mb_substr($terms, 0, 1200)]],
            // Stripe's "I agree to the terms", once the terms' address is set in Stripe.
            'consent_collection' => config('billing.terms_checkbox') === true ? ['terms_of_service' => 'required'] : null,
        ]));

        $session = $checkout->asStripeCheckoutSession();

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        return $user->billingPortalUrl($returnUrl);
    }

    public function cardFingerprint(string $subscriptionId): ?string
    {
        $subscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId, ['expand' => ['default_payment_method']]);
        $method = $subscription->default_payment_method;

        return is_object($method) && isset($method->card->fingerprint) ? (string) $method->card->fingerprint : null;
    }

    public function cancelNow(string $subscriptionId): void
    {
        Cashier::stripe()->subscriptions->cancel($subscriptionId);
    }

    public function latestPayment(User $user): ?array
    {
        // Plus's own subscription: a coaching package bills the same customer, and its invoice is
        // the coach's to refund (coaching.refund takes the coach's share back).
        $plus = $user->subscription('default')?->stripe_id;

        if ($user->stripe_id === null || $plus === null) {
            return null;
        }

        $invoice = Cashier::stripe()->invoices->all(['customer' => $user->stripe_id, 'subscription' => $plus, 'status' => 'paid', 'limit' => 1, 'expand' => ['data.payments']])->data[0] ?? null;

        if ($invoice === null) {
            return null;
        }

        // Older API versions name the PaymentIntent on the invoice; newer ones under `payments`.
        $intent = $invoice->payment_intent ?? ($invoice->payments->data[0]->payment->payment_intent ?? null);
        $intentId = is_string($intent) ? $intent : (is_object($intent) && isset($intent->id) ? (string) $intent->id : null);

        if ($intentId === null) {
            return null;
        }

        $charge = Cashier::stripe()->paymentIntents->retrieve($intentId, ['expand' => ['latest_charge']])->latest_charge;

        return [
            'paymentIntent' => $intentId,
            'cents' => (int) $invoice->amount_paid,
            'refunded' => is_object($charge) ? (int) $charge->amount_refunded : 0,
            'currency' => (string) $invoice->currency,
            'paidAt' => (int) ($invoice->status_transitions->paid_at ?? $invoice->created),
        ];
    }

    public function refund(string $paymentIntent, int $cents, string $idempotencyKey): void
    {
        Cashier::stripe()->refunds->create(['payment_intent' => $paymentIntent, 'amount' => $cents], ['idempotency_key' => $idempotencyKey]);
    }

    public function chargeCustomer(string $chargeId): ?string
    {
        $customer = Cashier::stripe()->charges->retrieve($chargeId)->customer;

        return is_string($customer) ? $customer : (is_object($customer) && isset($customer->id) ? (string) $customer->id : null);
    }
}
