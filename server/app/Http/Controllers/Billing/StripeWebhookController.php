<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\BillingEvents;
use App\Actions\Books\Books;
use App\Actions\Books\Ledger;
use App\Actions\Coaching\Engagements;
use App\Actions\Counts\RecordCounts;
use App\Models\Engagement;
use App\Models\StripeEvent;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe's webhook for Plus (/webhooks/stripe; its own secret, apart from Connect's later): Cashier
 * keeps its subscription records (and checks the signature), then BillingEvents does the rest.
 */
final class StripeWebhookController extends WebhookController
{
    /**
     * Never without the signing secret on a server (a forged event could grant Plus), and each
     * Stripe event once (Stripe redelivers).
     */
    public function handleWebhook(Request $request): Response
    {
        if (! app()->environment('local', 'testing') && (string) config('cashier.webhook.secret') === '') {
            report(new RuntimeException('Stripe webhook refused: STRIPE_WEBHOOK_SECRET is not set.'));

            return new Response('Webhook secret missing', 403);
        }

        $id = $request->input('id');

        if (is_string($id) && StripeEvent::query()->where('stripe_id', $id)->exists()) {
            return $this->successMethod();
        }

        $response = parent::handleWebhook($request);

        if (is_string($id) && $response->isSuccessful()) {
            try {
                (new StripeEvent(['stripe_id' => $id, 'type' => (string) $request->input('type')]))->save();
            } catch (UniqueConstraintViolationException) {
                // The same event handled twice at once: it's recorded either way.
                report(new RuntimeException('A Stripe event was handled twice at once: '.$id));
            }
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        // A coaching purchase (a review, a package or a pack) isn't Plus.
        if (($payload['data']['object']['metadata']['kind'] ?? null) === 'coaching') {
            app(Engagements::class)->checkoutCompleted((string) ($payload['data']['object']['id'] ?? ''));

            return $this->successMethod();
        }

        app(BillingEvents::class)->checkoutCompleted((string) ($payload['data']['object']['id'] ?? ''));

        return $this->successMethod();
    }

    /**
     * A delayed payment for a coaching Checkout went through (Plus takes cards only).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionAsyncPaymentSucceeded(array $payload): Response
    {
        if (($payload['data']['object']['metadata']['kind'] ?? null) === 'coaching') {
            app(Engagements::class)->checkoutCompleted((string) ($payload['data']['object']['id'] ?? ''));
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionAsyncPaymentFailed(array $payload): Response
    {
        if (($payload['data']['object']['metadata']['kind'] ?? null) === 'coaching') {
            app(Engagements::class)->checkoutFailed((string) ($payload['data']['object']['id'] ?? ''));
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        // A coaching package's subscription: never Cashier's (it would read as Plus).
        if (self::coaching($payload)) {
            return $this->successMethod();
        }

        $user = $this->owner($payload);
        // Counted once, when Cashier first records it (Stripe may send the event again).
        $new = $user !== null && ! $user->subscriptions()->where('stripe_id', (string) ($payload['data']['object']['id'] ?? ''))->exists();
        $response = parent::handleCustomerSubscriptionCreated($payload) ?? $this->successMethod();

        if ($new && in_array($payload['data']['object']['status'] ?? null, ['active', 'trialing'], true)) {
            app(RecordCounts::class)->server('plus_started', true, null);
        }

        if ($user !== null) {
            app(BillingEvents::class)->subscriptionStarted($user, (array) $payload['data']['object']);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        if (self::coaching($payload)) {
            return $this->successMethod();
        }

        $response = parent::handleCustomerSubscriptionUpdated($payload) ?? $this->successMethod();
        $user = $this->owner($payload);

        if ($user !== null) {
            app(BillingEvents::class)->subscriptionChanged($user, (array) $payload['data']['object']);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        if (self::coaching($payload)) {
            app(Engagements::class)->packageEnded((string) ($payload['data']['object']['id'] ?? ''));

            return $this->successMethod();
        }

        $response = parent::handleCustomerSubscriptionDeleted($payload) ?? $this->successMethod();
        $user = $this->owner($payload);

        if ($user !== null) {
            app(BillingEvents::class)->subscriptionEnded($user, (array) $payload['data']['object']);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCustomerSubscriptionTrialWillEnd(array $payload): Response
    {
        $user = $this->owner($payload);
        $trialEnd = $payload['data']['object']['trial_end'] ?? null;

        if ($user !== null) {
            app(BillingEvents::class)->trialEnding($user, is_int($trialEnd) ? $trialEnd : null);
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        // A package's failed month: Stripe retries, and ends the subscription if it never pays.
        if (self::coachingInvoice($payload) || Engagement::query()->where('stripe_subscription', self::invoiceSubscription($payload))->exists()) {
            return $this->successMethod();
        }

        $user = $this->owner($payload);

        if ($user !== null) {
            app(BillingEvents::class)->paymentFailed($user, (array) $payload['data']['object']);
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaid(array $payload): Response
    {
        $end = $payload['data']['object']['lines']['data'][0]['period']['end'] ?? null;

        // A package's month, even its first (which can arrive before its Checkout's event): never
        // Plus. It goes in the coaching ledger, once per invoice.
        if (app(Engagements::class)->packagePaid(self::invoiceSubscription($payload), is_int($end) ? $end : null) || self::coachingInvoice($payload)) {
            app(Ledger::class)->packageMonth((array) ($payload['data']['object'] ?? []));

            return $this->successMethod();
        }

        $user = $this->owner($payload);

        if ($user !== null) {
            $invoice = (array) $payload['data']['object'];
            app(BillingEvents::class)->paid($user, $invoice);

            // Stackapella's own books (when Hillel has connected Invoice Ninja).
            app(Books::class)->plusPaid($user, $invoice);
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * A refund on a charge: a Plus charge's is recorded in Stackapella's books (a coaching
     * charge's moves with its engagement, so this finds nothing for it).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $charge = (array) ($payload['data']['object'] ?? []);

        if (is_string($charge['id'] ?? null)) {
            app(Books::class)->plusRefunded($charge['id'], is_string($charge['payment_intent'] ?? null) ? $charge['payment_intent'] : null, (int) ($charge['amount_refunded'] ?? 0));
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeDisputeCreated(array $payload): Response
    {
        return $this->dispute($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeDisputeUpdated(array $payload): Response
    {
        return $this->dispute($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeDisputeClosed(array $payload): Response
    {
        return $this->dispute($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispute(array $payload): Response
    {
        // A chargeback on coaching: the coach's share never moves after it, and Hillel sees it.
        $charge = $payload['data']['object']['charge'] ?? null;

        if (is_string($charge)) {
            app(Engagements::class)->disputed($charge);
        }

        $dispute = (array) $payload['data']['object'];
        // A dispute names its charge, not the customer: ask Stripe whose charge it is.
        $charge = $dispute['charge'] ?? null;
        $customer = is_string($charge) ? app(BillingGateway::class)->chargeCustomer($charge) : null;
        $user = is_string($customer) ? User::query()->where('stripe_id', $customer)->first() : null;

        app(BillingEvents::class)->disputed($user, $dispute);

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function owner(array $payload): ?User
    {
        $customer = $payload['data']['object']['customer'] ?? null;

        return is_string($customer) ? User::query()->where('stripe_id', $customer)->first() : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function coaching(array $payload): bool
    {
        $metadata = (array) ($payload['data']['object']['metadata'] ?? []);

        return ($metadata['type'] ?? null) === 'coaching' || ($metadata['kind'] ?? null) === 'coaching';
    }

    /**
     * An invoice for a coaching package, by its subscription's metadata (in Stripe's older and newer
     * shapes, or on its lines).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function coachingInvoice(array $payload): bool
    {
        $invoice = (array) ($payload['data']['object'] ?? []);
        $metadata = [
            $invoice['subscription_details']['metadata'] ?? null,
            $invoice['parent']['subscription_details']['metadata'] ?? null,
            $invoice['lines']['data'][0]['metadata'] ?? null,
        ];

        foreach ($metadata as $found) {
            if (is_array($found) && (($found['type'] ?? null) === 'coaching' || ($found['kind'] ?? null) === 'coaching')) {
                return true;
            }
        }

        return false;
    }

    /**
     * An invoice's subscription, in Stripe's older and newer shapes.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function invoiceSubscription(array $payload): string
    {
        $invoice = (array) ($payload['data']['object'] ?? []);
        $subscription = $invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null;

        return is_string($subscription) ? $subscription : '';
    }
}
