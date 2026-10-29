<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\SecurityEventType;
use App\Mail\Account\PaymentFailed;
use App\Mail\Account\TrialEnding;
use App\Models\AuditLog;
use App\Models\BillingDispute;
use App\Models\CheckoutSession;
use App\Models\TrialFingerprint;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Trust\SecurityLog;
use App\Support\Usage\Plans;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * What Stripe's webhook tells us beyond Cashier's own bookkeeping (docs/backend-plan.md D8;
 * docs/trust-and-safety-plan.md, "Payments"): one trial per account and per card, the renewal and
 * retry dates, how Plus ended, disputes, and the two emails we send (Stripe sends the receipts).
 * Every step is safe to receive twice: Stripe redelivers.
 */
final class BillingEvents
{
    public function __construct(private readonly BillingGateway $gateway, private readonly Plans $plans, private readonly SecurityLog $securityLog) {}

    public function checkoutCompleted(string $sessionId): void
    {
        CheckoutSession::query()->where('stripe_id', $sessionId)->whereNull('completed_at')->update(['completed_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $subscription  Stripe's subscription object
     */
    public function subscriptionStarted(User $user, array $subscription): void
    {
        $id = (string) $subscription['id'];
        $user->forceFill(['plus_ended_at' => null, 'plus_ended_reason' => null])->save();

        if (($subscription['status'] ?? null) === 'trialing' && ! $this->trialAllowed($user, $id)) {
            return;
        }

        $this->subscriptionChanged($user, $subscription);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function subscriptionChanged(User $user, array $subscription): void
    {
        $id = (string) $subscription['id'];
        $periodEnd = $subscription['current_period_end'] ?? ($subscription['items']['data'][0]['current_period_end'] ?? null);
        $user->forceFill(['plus_renews_at' => is_int($periodEnd) ? Carbon::createFromTimestamp($periodEnd) : $user->plus_renews_at])->save();

        // A card-less trial gets its card later: it's checked then, as at Checkout.
        if ($user->trial_subscription_id === $id && ($subscription['default_payment_method'] ?? null) !== null && ! TrialFingerprint::query()->where('subscription_id', $id)->exists()) {
            $this->recordTrialCard($user, $id);
        }

        if (($subscription['status'] ?? null) === 'active' && $user->billing_notice === 'payment_failed') {
            $user->forceFill(['billing_notice' => null, 'past_due_until' => null])->save();
        }

        $this->plans->readOnlyChanged($user);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function subscriptionEnded(User $user, array $subscription): void
    {
        $reason = match (true) {
            BillingDispute::query()->where('user_id', $user->id)->where('created_at', '>', now()->subDay())->exists() => 'dispute',
            ($subscription['cancellation_details']['reason'] ?? null) === 'payment_failed' => 'payment_failed',
            default => 'canceled',
        };

        // A trial cancelled for its card isn't an ending: they never had Plus.
        $refused = $user->billing_notice === 'card_had_trial' && $user->billing_notice_at?->gt(now()->subMinutes(10));

        if (! $refused && ! $this->plans->isPlus($user)) {
            $user->forceFill(['plus_ended_at' => now(), 'plus_ended_reason' => $reason, 'past_due_until' => null])->save();
            // Which projects stay editable is settled now, the most recently edited (the person
            // can choose others), so editing one later doesn't move the line.
            $this->plans->settleEditable($user);
        }

        $this->plans->readOnlyChanged($user);
    }

    public function trialEnding(User $user, ?int $trialEnd): void
    {
        if ($user->email !== null && $user->email_verified_at !== null) {
            Mail::to($user->email)->send(new TrialEnding(Carbon::createFromTimestamp($trialEnd ?? now()->addDays(3)->getTimestamp())->settings(['locale' => $user->locale])->isoFormat('LL')));
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function paymentFailed(User $user, array $invoice): void
    {
        $retry = $invoice['next_payment_attempt'] ?? null;
        $user->forceFill(['billing_notice' => 'payment_failed', 'past_due_until' => is_int($retry) ? Carbon::createFromTimestamp($retry) : now()->addWeek()])->save();
        // Counted, so a burst (card testing) shows in the security events and their alert.
        $this->securityLog->record(SecurityEventType::PaymentFailed, $user, null, ['invoice' => $invoice['id'] ?? null]);

        if ($user->email !== null && $user->email_verified_at !== null) {
            Mail::to($user->email)->send(new PaymentFailed);
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function paid(User $user, array $invoice): void
    {
        if ((int) ($invoice['amount_paid'] ?? 0) > 0 && $user->plus_paid_at === null) {
            $user->forceFill(['plus_paid_at' => now()])->save();
        }
    }

    /**
     * A chargeback: the account drops to trust level 0 and Plus ends at once, and it shows in admin.
     * Applied once, the first time the account is known.
     *
     * @param  array<string, mixed>  $dispute
     */
    public function disputed(?User $user, array $dispute): void
    {
        $record = BillingDispute::query()->firstOrNew(['stripe_id' => (string) $dispute['id']]);
        $record->fill(['amount_cents' => (int) ($dispute['amount'] ?? 0), 'currency' => (string) ($dispute['currency'] ?? 'usd'), 'reason' => (string) ($dispute['reason'] ?? 'unknown'), 'status' => (string) ($dispute['status'] ?? 'needs_response')]);
        $record->forceFill(['user_id' => $user !== null ? $user->id : $record->user_id])->save();

        if ($user === null || $record->applied_at !== null) {
            return;
        }

        $user->forceFill(['trust_level_override' => 0, 'trust_note' => 'Chargeback '.$record->stripe_id])->save();
        AuditLog::record('billing.disputed', $user, null, after: ['dispute' => $record->stripe_id]);

        $this->endPlusNow($user, 'dispute');
        $record->forceFill(['applied_at' => now()])->save();
    }

    /**
     * Plus through Stripe ends now, not at the end of the period (a chargeback, or a refund from
     * admin), and what's over the free plan's limits becomes read-only.
     *
     * @param  'dispute'|'refunded'  $reason
     */
    public function endPlusNow(User $user, string $reason): void
    {
        $subscription = $user->subscription('default');

        if ($subscription !== null && $subscription->valid()) {
            $this->gateway->cancelNow($subscription->stripe_id);
            $subscription->markAsCanceled();
        }

        $user->forceFill(['plus_ended_at' => now(), 'plus_ended_reason' => $reason])->save();
        $this->plans->settleEditable($user);
        $this->plans->readOnlyChanged($user);
    }

    /**
     * One trial per account and per card: a second trial on the account, or on a card that had
     * one, is cancelled at once with no charge. A redelivered event finds its own trial and passes.
     */
    private function trialAllowed(User $user, string $subscriptionId): bool
    {
        if ($user->trial_used_at !== null && $user->trial_subscription_id !== $subscriptionId) {
            $this->refuseTrial($user, $subscriptionId, 'account_had_trial');

            return false;
        }

        if ($user->trial_subscription_id === null) {
            $user->forceFill(['trial_used_at' => now(), 'trial_subscription_id' => $subscriptionId])->save();
        }

        return $this->recordTrialCard($user, $subscriptionId);
    }

    private function recordTrialCard(User $user, string $subscriptionId): bool
    {
        $fingerprint = $this->gateway->cardFingerprint($subscriptionId);

        if ($fingerprint === null) {
            return true;
        }

        $known = TrialFingerprint::query()->where('fingerprint', $fingerprint)->first();

        if ($known !== null && $known->subscription_id !== $subscriptionId) {
            $this->refuseTrial($user, $subscriptionId, 'card_had_trial');

            return false;
        }

        if ($known === null) {
            try {
                (new TrialFingerprint(['fingerprint' => $fingerprint]))->forceFill(['user_id' => $user->id, 'subscription_id' => $subscriptionId])->save();
            } catch (UniqueConstraintViolationException) {
                // The same card's trial a moment ago, elsewhere: this one goes.
                $this->refuseTrial($user, $subscriptionId, 'card_had_trial');

                return false;
            }
        }

        return true;
    }

    private function refuseTrial(User $user, string $subscriptionId, string $why): void
    {
        $this->gateway->cancelNow($subscriptionId);

        if ($user->trial_subscription_id === $subscriptionId) {
            $user->forceFill(['trial_subscription_id' => null, 'trial_used_at' => null])->save();
        }

        $user->forceFill(['billing_notice' => 'card_had_trial', 'billing_notice_at' => now()])->save();
        $this->securityLog->record(SecurityEventType::TrialRefused, $user, null, ['reason' => $why]);
    }
}
