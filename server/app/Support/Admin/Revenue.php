<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\CheckoutSession;
use App\Models\CoachingPayment;
use App\Models\Engagement;
use App\Models\StoreSubscription;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Growth > Revenue (docs/admin-analytics-plan.md, slice 2), from our own tables only:
 * `subscriptions` (the web, through Stripe), `store_subscriptions` (production only) and the
 * coaching ledger, `coaching_payments`, which also holds pack sales.
 *
 * - A web subscription's price is the plan's list price (config billing.amounts) in the currency
 *   of the person's first Checkout, as the app shows it; annual plans count a twelfth a month.
 *   Currencies are kept apart, never converted.
 * - The stores don't tell us what a subscriber pays (it's the territory's price), so a store
 *   subscription is counted in the subscribers but not in MRR: `unpriced` says how many, never a
 *   guessed amount. A web one whose currency or plan isn't known is unpriced too.
 * - Past months are worked out from each subscription's start, trial end and end, so a payment
 *   that failed and recovered inside a month isn't seen.
 *
 * @phpstan-type Web array{user: int, status: string, plan: string|null, paidFrom: Carbon, ends: Carbon|null, trialEnds: Carbon|null}
 */
final class Revenue
{
    /** @var array<int, string> each user's first Checkout's currency */
    private array $currencies = [];

    /**
     * @return array{
     *     mrr: list<array{currency: string, cents: int}>,
     *     arr: list<array{currency: string, cents: int}>,
     *     monthAgo: list<array{currency: string, cents: int}>,
     *     unpriced: int,
     *     subscribers: list<array{state: string, store: string, plan: string, count: int}>,
     *     months: list<array{month: string, mrr: list<array{currency: string, cents: int}>, unpriced: int, new: int, lost: int}>,
     *     oneOff: list<array{month: string, kind: string, currency: string, payments: int, gross: int, ours: int}>
     * }
     */
    public function show(): array
    {
        $now = now();
        [$web, $stores] = self::load();
        $this->currencies = CheckoutSession::query()->whereNotNull('completed_at')->whereIn('user_id', $web->pluck('user'))->orderByDesc('id')
            ->pluck('currency', 'user_id')->map(fn (mixed $c): string => strtolower((string) $c))->all();

        [$mrr, $unpriced] = $this->mrrAt($now, $web, $stores);
        [$monthAgo] = $this->mrrAt($now->copy()->subDays(30), $web, $stores);
        $arr = array_map(fn (float $cents): float => $cents * 12, $mrr);

        $months = [];
        $first = ReportDay::now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            $from = $first->copy()->addMonths($i);
            $to = $from->copy()->endOfMonth();
            [$atEnd, $unpricedAtEnd] = $this->mrrAt($to->lt($now) ? $to : $now, $web, $stores);
            $months[] = [
                'month' => $from->format('Y-m'),
                'mrr' => self::money($atEnd),
                'unpriced' => $unpricedAtEnd,
                'new' => $web->filter(fn (array $s): bool => self::webPaid($s) && self::within($s['paidFrom'], $from, $to, $now))->count()
                    + $stores->filter(fn (StoreSubscription $s): bool => self::within($s->created_at, $from, $to, $now))->count(),
                'lost' => $web->filter(fn (array $s): bool => self::webPaid($s) && self::within($s['ends'], $from, $to, $now))->count()
                    + $stores->filter(fn (StoreSubscription $s): bool => self::within(self::storeEnd($s), $from, $to, $now))->count(),
            ];
        }

        return [
            'mrr' => self::money($mrr),
            'arr' => self::money($arr),
            'monthAgo' => self::money($monthAgo),
            'unpriced' => $unpriced,
            'subscribers' => $this->subscribers($web, $stores, $now),
            'months' => $months,
            'oneOff' => $this->oneOff($first),
        ];
    }

    /**
     * admin.churn.show (docs/admin-analytics-plan.md, slice 4): the last 12 months (the report
     * timezone), oldest first: subscriptions running at each month's start, those lost in it by
     * store, and web trials that ended in it and how many of them went on to pay. Rebuilt from each
     * subscription's dates; a cancellation's reason and a resumed subscription aren't recorded.
     *
     * @return list<array{month: string, payingAtStart: int, lost: array{web: int, apple: int, google: int}, trialsEnded: int, trialsConverted: int}>
     */
    public function churn(): array
    {
        $now = now();
        [$web, $stores] = self::load();
        $first = ReportDay::now()->startOfMonth()->subMonths(11);
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $from = $first->copy()->addMonths($i);
            $to = $from->copy()->endOfMonth();
            $at = $from->copy()->utc();
            $trials = $web->filter(fn (array $s): bool => self::within($s['trialEnds'], $from, $to, $now));
            $months[] = [
                'month' => $from->format('Y-m'),
                'payingAtStart' => $web->filter(fn (array $s): bool => self::webBilledAt($s, $at))->count()
                    + $stores->filter(fn (StoreSubscription $s): bool => $s->created_at !== null && $s->created_at->lte($at) && ((self::storeEnd($s)?->gt($at)) ?? true))->count(),
                'lost' => [
                    'web' => $web->filter(fn (array $s): bool => self::webPaid($s) && self::within($s['ends'], $from, $to, $now))->count(),
                    'apple' => $stores->filter(fn (StoreSubscription $s): bool => $s->store === StoreSubscription::APPLE && self::within(self::storeEnd($s), $from, $to, $now))->count(),
                    'google' => $stores->filter(fn (StoreSubscription $s): bool => $s->store === StoreSubscription::GOOGLE && self::within(self::storeEnd($s), $from, $to, $now))->count(),
                ],
                'trialsEnded' => $trials->count(),
                'trialsConverted' => $trials->filter(fn (array $s): bool => self::webPaid($s))->count(),
            ];
        }

        return $months;
    }

    /**
     * @return array{0: Collection<int, Web>, 1: Collection<int, StoreSubscription>}
     */
    private static function load(): array
    {
        $web = DB::table('subscriptions')->where('type', 'default')->get(['user_id', 'stripe_status', 'stripe_price', 'trial_ends_at', 'ends_at', 'created_at'])
            ->map(fn (object $row): array => self::web((array) $row));

        return [$web, StoreSubscription::query()->where('environment', 'Production')->get()];
    }

    /**
     * What was billed each month at `$at`, by currency, and how many billed subscriptions have no
     * price we know.
     *
     * @param  Collection<int, Web>  $web
     * @param  Collection<int, StoreSubscription>  $stores
     * @return array{0: array<string, float>, 1: int}
     */
    private function mrrAt(Carbon $at, Collection $web, Collection $stores): array
    {
        $cents = [];
        $unpriced = 0;

        foreach ($web as $s) {
            if (! self::webBilledAt($s, $at)) {
                continue;
            }

            $currency = $this->currencies[$s['user']] ?? null;
            $plan = $s['plan'];
            $amount = $currency !== null && $plan !== null ? config('billing.amounts.'.$currency.'.'.$plan) : null;

            if (! is_int($amount)) {
                $unpriced++;

                continue;
            }

            $cents[$currency] = ($cents[$currency] ?? 0.0) + ($plan === 'year' ? $amount / 12 : $amount);
        }

        $unpriced += $stores->filter(fn (StoreSubscription $s): bool => $s->created_at !== null && $s->created_at->lte($at) && ((self::storeEnd($s)?->gt($at)) ?? true))->count();

        return [$cents, $unpriced];
    }

    /**
     * @param  array<string, float>  $cents
     * @return list<array{currency: string, cents: int}>
     */
    private static function money(array $cents): array
    {
        ksort($cents);

        return array_values(array_map(fn (string $c, float $v): array => ['currency' => $c, 'cents' => (int) round($v)], array_keys($cents), $cents));
    }

    /**
     * Today's subscribers by state, store and plan.
     *
     * @param  Collection<int, Web>  $web
     * @param  Collection<int, StoreSubscription>  $stores
     * @return list<array{state: string, store: string, plan: string, count: int}>
     */
    private function subscribers(Collection $web, Collection $stores, Carbon $now): array
    {
        $rows = [];
        $add = function (?string $state, string $store, ?string $plan) use (&$rows): void {
            if ($state === null) {
                return;
            }

            $key = $state.'|'.$store.'|'.($plan ?? 'unknown');
            $rows[$key] = ['state' => $state, 'store' => $store, 'plan' => $plan ?? 'unknown', 'count' => ($rows[$key]['count'] ?? 0) + 1];
        };

        foreach ($web as $s) {
            $add(match (true) {
                $s['status'] === 'trialing' => 'trialing',
                $s['status'] === 'past_due' => 'pastDue',
                $s['status'] === 'active' && $s['ends'] === null => 'paying',
                $s['status'] === 'active' && $s['ends']?->gt($now) === true => 'cancelling',
                default => null,
            }, 'web', $s['plan']);
        }

        foreach ($stores as $s) {
            $add(match ($s->status) {
                StoreSubscription::ACTIVE => $s->will_renew ? 'paying' : 'cancelling',
                StoreSubscription::GRACE, StoreSubscription::RETRYING => 'pastDue',
                default => null,
            }, $s->store, in_array($s->interval, ['month', 'year'], true) ? $s->interval : null);
        }

        $order = ['paying' => 0, 'trialing' => 1, 'pastDue' => 2, 'cancelling' => 3];
        usort($rows, fn (array $a, array $b): int => [$order[$a['state']], $a['store'], $a['plan']] <=> [$order[$b['state']], $b['store'], $b['plan']]);

        return $rows;
    }

    /**
     * Coaching and pack sales by month (UTC) and currency, refunds taken off: `gross` what singers
     * paid, `ours` our commission. A coach's backfilled sales are their books, never ours.
     *
     * @return list<array{month: string, kind: string, currency: string, payments: int, gross: int, ours: int}>
     */
    private function oneOff(Carbon $from): array
    {
        // Months in the report's zone, so worked out here rather than by MySQL (whose zone tables
        // may not be loaded).
        $zone = ReportDay::zone();
        $groups = [];
        $rows = DB::table('coaching_payments')
            ->leftJoin('engagements', 'engagements.id', '=', 'coaching_payments.engagement_id')
            ->where('coaching_payments.backfilled', false)
            ->where('coaching_payments.paid_at', '>=', $from->copy()->utc())
            ->get(['coaching_payments.paid_at', 'coaching_payments.kind', 'coaching_payments.currency', 'coaching_payments.cents', 'coaching_payments.fee_cents', 'engagements.kind as engagement_kind']);

        foreach ($rows as $r) {
            $month = Carbon::parse((string) $r->paid_at, 'UTC')->setTimezone($zone)->format('Y-m');
            $kind = $r->engagement_kind === Engagement::PACK ? 'packs' : 'coaching';
            $currency = strtolower((string) $r->currency);
            $key = $month.'|'.$kind.'|'.$currency;
            $groups[$key] ??= ['month' => $month, 'kind' => $kind, 'currency' => $currency, 'payments' => 0, 'gross' => 0, 'ours' => 0];
            $groups[$key]['payments'] += $r->kind === CoachingPayment::SALE ? 1 : 0;
            $groups[$key]['gross'] += (int) $r->cents;
            $groups[$key]['ours'] += (int) $r->fee_cents;
        }

        ksort($groups);

        return array_values($groups);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Web
     */
    private static function web(array $row): array
    {
        $plan = array_search($row['stripe_price'], (array) config('billing.prices'), true);
        $start = self::date($row['created_at']) ?? now();
        $trial = self::date($row['trial_ends_at']);

        return [
            'user' => (int) $row['user_id'],
            'status' => (string) $row['stripe_status'],
            'plan' => is_string($plan) ? $plan : null,
            // When the first payment was taken: the end of the trial, or the start.
            'paidFrom' => $trial !== null && $trial->gt($start) ? $trial : $start,
            'ends' => self::date($row['ends_at']),
            'trialEnds' => $trial !== null && $trial->gt($start) ? $trial : null,
        ];
    }

    /**
     * Ever paid for: past any trial before it ended, and not a Checkout that never completed.
     *
     * @param  Web  $s
     */
    private static function webPaid(array $s): bool
    {
        return ! in_array($s['status'], ['incomplete', 'incomplete_expired'], true) && ($s['ends'] === null || $s['ends']->gt($s['paidFrom']));
    }

    /**
     * @param  Web  $s
     */
    private static function webBilledAt(array $s, Carbon $at): bool
    {
        return self::webPaid($s) && $s['status'] !== 'unpaid' && $s['paidFrom']->lte($at) && ($s['ends'] === null || $s['ends']->gt($at));
    }

    /** When a store subscription stopped running, or null while it runs. */
    private static function storeEnd(StoreSubscription $s): ?Carbon
    {
        if (! in_array($s->status, [StoreSubscription::EXPIRED, StoreSubscription::REVOKED], true)) {
            return null;
        }

        return $s->refunded_at ?? $s->expires_at ?? $s->updated_at;
    }

    private static function within(?Carbon $at, Carbon $from, Carbon $to, Carbon $now): bool
    {
        return $at !== null && $at->gte($from) && $at->lte($to) && $at->lte($now);
    }

    private static function date(mixed $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse((string) $value, 'UTC');
    }
}
