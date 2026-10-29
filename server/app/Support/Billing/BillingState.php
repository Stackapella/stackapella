<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\CheckoutSession;
use App\Models\StoreSubscription;
use App\Models\User;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Illuminate\Support\Carbon;

/**
 * billing.show and billing.prices (contract/schemas/billing.show.json): what the account's plan
 * is, from Cashier's records and what the webhooks told us, never from a call to Stripe.
 */
final class BillingState
{
    public function __construct(private readonly Plans $plans, private readonly TrustLevel $trust) {}

    /**
     * @return list<array{interval: string, currency: string, amountCents: int, taxInclusive: bool}>
     */
    public static function prices(): array
    {
        $prices = [];

        foreach ((array) config('billing.amounts') as $currency => $amounts) {
            foreach (['year', 'month'] as $interval) {
                $prices[] = ['interval' => $interval, 'currency' => (string) $currency, 'amountCents' => (int) $amounts[$interval], 'taxInclusive' => (bool) $amounts['tax_inclusive']];
            }
        }

        return $prices;
    }

    /**
     * The trial this account could have, or null.
     *
     * @return array{days: int, intervals: list<string>}|null
     */
    public function trial(?User $user): ?array
    {
        if ($user !== null && ($user->trial_used_at !== null || $this->plans->isPlus($user))) {
            return null;
        }

        // Yearly with a card for anyone; monthly without a card only from trust level 2.
        $intervals = ['year'];

        if ($user !== null && $this->trust->of($user) >= (int) config('billing.no_card_trial_level')) {
            $intervals[] = 'month';
        }

        return ['days' => (int) config('billing.trial_days'), 'intervals' => $intervals];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user): array
    {
        $subscription = $user->subscription('default');
        $valid = $subscription !== null && $subscription->valid();
        $checkout = CheckoutSession::query()->where('user_id', $user->id)->latest('id')->first();
        $pending = ! $valid && $checkout !== null && $checkout->completed_at === null && $checkout->created_at?->gt(now()->subMinutes((int) config('billing.pending_minutes')));

        $endsAt = $valid ? $subscription->ends_at : null;
        $plus = $this->plans->isPlus($user);
        // Plus bought in an app: the store subscription giving it the latest.
        $store = StoreSubscription::query()->with('user')->where('user_id', $user->id)->whereIn('status', [StoreSubscription::ACTIVE, StoreSubscription::GRACE])->get()
            ->filter(fn (StoreSubscription $row): bool => $row->plusUntil()?->isFuture() === true)->sortByDesc(fn (StoreSubscription $row): int => (int) $row->plusUntil()?->getTimestamp())->first();
        $paidUntil = $user->getAttributes()['plus_paid_until'] ?? null;

        $status = match (true) {
            $valid && $subscription->onTrial() => 'trialing',
            $valid && $subscription->pastDue() => 'past_due',
            $valid && $subscription->onGracePeriod() => 'canceled',
            $valid, $store !== null => 'active',
            $pending => 'pending',
            ! $plus && $user->plus_ended_at !== null => 'ended',
            default => 'none',
        };

        // Offline, Plus lasts the offline days, or only to its end when nothing renews it.
        $offlineUntil = $plus ? now()->addDays((int) config('billing.offline_days')) : null;
        $renews = ($valid && $endsAt === null) || $store?->will_renew === true;
        $ends = collect([$endsAt, $store?->plusUntil()])->filter()->max();

        if ($offlineUntil !== null && ! $renews && $ends !== null && $ends->lt($offlineUntil)) {
            $offlineUntil = Carbon::instance($ends);
        }

        // The plan from the subscription itself (the portal may have switched it), the currency
        // from the first Checkout (Stripe ties a customer to it).
        $interval = $valid ? (array_search($subscription->stripe_price, (array) config('billing.prices'), true) ?: $checkout?->interval) : null;
        $currency = CheckoutSession::query()->where('user_id', $user->id)->whereNotNull('completed_at')->oldest('id')->value('currency') ?? $checkout?->currency;
        $paid = $valid && is_string($interval) && is_string($currency) ? (int) (config('billing.amounts.'.$currency.'.'.$interval) ?? 0) : 0;
        $notice = match (true) {
            $user->billing_notice === 'card_had_trial' && $user->billing_notice_at?->gt(now()->subDays((int) config('billing.notice_days'))) => 'card_had_trial',
            $status === 'past_due' => 'payment_failed',
            // Paid in two places (the web and a store), both renewing: the app says so.
            $valid && ! $subscription->onGracePeriod() && $store !== null && $store->will_renew => 'plus_twice',
            default => null,
        };

        return [
            'plan' => $plus ? 'plus' : 'free',
            'status' => $status,
            // Where Plus comes from now: the web, a store, a coach's, an invoice, or the demo.
            'source' => ! $plus ? null : match (true) {
                // Canceled on the web but renewing in a store: the store's Plus is the one that lasts.
                $valid && ! ($subscription->onGracePeriod() && $store?->will_renew === true) => 'stripe',
                $store !== null => $store->store,
                ($user->getAttributes()['coach_active_at'] ?? null) !== null => 'coach',
                is_string($paidUntil) && $paidUntil > now()->toDateTimeString() => 'invoice',
                default => 'demo',
            },
            'store' => $store !== null ? [
                'store' => $store->store,
                'productId' => $store->product_id,
                'interval' => $store->interval,
                'expiresAt' => $this->iso($store->plusUntil()),
                'willRenew' => $store->will_renew,
                'inGracePeriod' => $store->status === StoreSubscription::GRACE,
            ] : null,
            // The apps set it as Apple's appAccountToken and Google's obfuscatedAccountId.
            'storeAccountToken' => (string) $user->store_account_token,
            'subscription' => $valid && is_string($interval) && is_string($currency) ? [
                'interval' => $interval,
                'currency' => $currency,
                'amountCents' => $paid,
                'renewsAt' => $status === 'canceled' ? null : $this->iso($subscription->onTrial() ? $subscription->trial_ends_at : $user->plus_renews_at),
                'endsAt' => $this->iso($endsAt),
                'trialEndsAt' => $subscription->onTrial() ? $this->iso($subscription->trial_ends_at) : null,
            ] : null,
            'pastDueUntil' => $status === 'past_due' ? $this->iso($user->past_due_until) : null,
            'endedAt' => $status === 'ended' ? $this->iso($user->plus_ended_at) : null,
            'endedReason' => $status === 'ended' ? $user->plus_ended_reason : null,
            'trial' => $this->trial($user),
            'notice' => $notice,
            'checkedAt' => now()->utc()->toIso8601ZuluString(),
            'plusOfflineUntil' => $this->iso($offlineUntil),
        ];
    }

    private function iso(?\DateTimeInterface $at): ?string
    {
        return $at !== null ? Carbon::instance($at)->utc()->toIso8601ZuluString() : null;
    }
}
