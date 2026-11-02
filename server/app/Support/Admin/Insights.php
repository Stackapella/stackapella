<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\CoachingPayment;
use App\Models\Score;
use App\Models\StoreSubscription;
use App\Models\Take;
use App\Models\User;
use App\Support\Stats\Counts;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin analytics slices 3 and 5 (docs/admin-analytics-plan.md), from our own tables only, the
 * admins' own accounts left out, days in the report timezone: results by sign-up month, and the
 * activity page (a feed of what happened, 24 hourly bars, feature use, free accounts near their
 * limits).
 */
final class Insights
{
    /** Feature kinds on the activity page, from the anonymous daily counts. */
    public const FEATURES = ['harmonise_used', 'export_done', 'share_created', 'take_recorded', 'hum_found', 'track_add', 'sharpen_up', 'then_now', 'sheet_import', 'daily_sung', 'result_shared'];

    /**
     * admin.cohorts.show: the last 12 sign-up months, oldest first.
     *
     * @return list<array{month: string, signedUp: int, finishedSong: int, secondSong: int, active: int, plus: int, medianDaysToFirstSong: float|null}>
     */
    public function cohorts(): array
    {
        $zone = ReportDay::zone();
        $first = ReportDay::now()->startOfMonth()->subMonths(11);
        $users = User::query()->where('is_admin', false)->where('created_at', '>=', $first->copy()->utc())
            ->get(['id', 'created_at', 'plus_paid_at', 'plus_paid_until'])->keyBy('id');
        $ids = $users->keys()->all();

        // Each person's finished songs: the first one's time, and how many songs (by template).
        $songs = [];

        foreach (Counts::finished()->whereIn('user_id', $ids) as $song) {
            $mine = $songs[$song->user_id] ?? ['first' => null, 'templates' => []];
            $at = $song->finished_at !== null ? Carbon::parse((string) $song->finished_at, 'UTC') : null;
            $mine['first'] = $at !== null && ($mine['first'] === null || $at->lt($mine['first'])) ? $at : $mine['first'];
            $mine['templates'][$song->template_slug] = true;
            $songs[$song->user_id] = $mine;
        }

        $active = array_flip(Counts::activeIds(now()->subDays(30))->all());
        $stores = StoreSubscription::query()->where('environment', 'Production')->whereIn('user_id', $ids)->pluck('user_id')->flip();
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $months[$first->copy()->addMonths($i)->format('Y-m')] = ['signedUp' => 0, 'finishedSong' => 0, 'secondSong' => 0, 'active' => 0, 'plus' => 0, 'days' => []];
        }

        foreach ($users as $user) {
            $month = $user->created_at?->copy()->setTimezone($zone)->format('Y-m');

            if ($month === null || ! isset($months[$month])) {
                continue;
            }

            $row = &$months[$month];
            $row['signedUp']++;
            $mine = $songs[$user->id] ?? null;

            if ($mine !== null) {
                $row['finishedSong']++;
                $row['secondSong'] += count($mine['templates']) >= 2 ? 1 : 0;

                if ($mine['first'] !== null && $user->created_at !== null) {
                    $row['days'][] = max(0.0, $user->created_at->diffInSeconds($mine['first'], false) / 86400);
                }
            }

            $row['active'] += isset($active[$user->id]) ? 1 : 0;
            $row['plus'] += $user->plus_paid_at !== null || $user->getAttributes()['plus_paid_until'] !== null || $stores->has($user->id) ? 1 : 0;
            unset($row);
        }

        return array_map(fn (string $month, array $r): array => [
            'month' => $month, 'signedUp' => $r['signedUp'], 'finishedSong' => $r['finishedSong'], 'secondSong' => $r['secondSong'],
            'active' => $r['active'], 'plus' => $r['plus'], 'medianDaysToFirstSong' => self::median($r['days']),
        ], array_keys($months), $months);
    }

    /**
     * admin.activity.show: the latest things that happened, the last 24 hours by hour, the last 30
     * days' feature use, and free accounts at or near their project limit.
     *
     * @return array{feed: list<array{at: string, kind: string, person: array{id: string, name: string}|null, detail: string|null}>, hours: list<array{hour: string, takes: int, scores: int, signUps: int}>, features: list<array{kind: string, guest: int, account: int}>, limits: array{freeAccounts: int, projectsAtLimit: int, projectsNearLimit: int, projectLimit: int}}
     */
    public function activity(): array
    {
        return ['feed' => $this->feed(), 'hours' => $this->hours(), 'features' => $this->features(), 'limits' => $this->limits()];
    }

    /**
     * @return list<array{at: string, kind: string, person: array{id: string, name: string}|null, detail: string|null}>
     */
    private function feed(): array
    {
        $admins = Counts::admins();
        $person = static fn (?User $u): ?array => $u !== null ? ['id' => $u->sqid(), 'name' => $u->name] : null;
        $iso = static fn (mixed $at): string => Carbon::parse((string) $at, 'UTC')->toIso8601ZuluString();
        $events = [];

        foreach (User::query()->where('is_admin', false)->latest('id')->limit(30)->get(['id', 'name', 'created_at']) as $u) {
            $events[] = ['at' => $iso($u->created_at), 'kind' => 'sign_up', 'person' => $person($u), 'detail' => null];
        }

        $subs = DB::table('subscriptions')->where('type', 'default')->whereNotIn('user_id', $admins)->whereNotIn('stripe_status', ['incomplete', 'incomplete_expired'])
            ->latest('id')->limit(30)->get(['user_id', 'stripe_status', 'trial_ends_at', 'created_at', 'ends_at']);
        $stores = StoreSubscription::query()->where('environment', 'Production')->whereNotIn('user_id', $admins)->latest('id')->limit(30)->get(['user_id', 'store', 'created_at']);
        $sales = CoachingPayment::query()->where('backfilled', false)->where('kind', CoachingPayment::SALE)->latest('paid_at')->limit(30)->get(['cents', 'currency', 'paid_at']);
        $years = DB::table('school_redemptions')->latest('id')->limit(30)->get(['user_id', 'created_at']);
        $people = User::query()->whereIn('id', [...$subs->pluck('user_id'), ...$stores->pluck('user_id'), ...$years->pluck('user_id')])->get(['id', 'name'])->keyBy('id');

        foreach ($subs as $s) {
            $events[] = ['at' => $iso($s->created_at), 'kind' => $s->trial_ends_at !== null ? 'trial_started' : 'plus_started', 'person' => $person($people->get($s->user_id)), 'detail' => 'web'];

            if ($s->ends_at !== null && Carbon::parse((string) $s->ends_at, 'UTC')->isPast()) {
                $events[] = ['at' => $iso($s->ends_at), 'kind' => 'plus_ended', 'person' => $person($people->get($s->user_id)), 'detail' => 'web'];
            }
        }

        foreach ($stores as $s) {
            $events[] = ['at' => $iso($s->created_at), 'kind' => 'plus_started', 'person' => $person($people->get($s->user_id)), 'detail' => $s->store];
        }

        foreach ($sales as $sale) {
            $events[] = ['at' => $iso($sale->paid_at), 'kind' => 'coaching_sale', 'person' => null, 'detail' => strtoupper($sale->currency).' '.number_format($sale->cents / 100, 2, '.', '')];
        }

        foreach ($years as $y) {
            $events[] = ['at' => $iso($y->created_at), 'kind' => 'school_year', 'person' => $person($people->get($y->user_id)), 'detail' => null];
        }

        usort($events, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return array_slice($events, 0, 50);
    }

    /**
     * @return list<array{hour: string, takes: int, scores: int, signUps: int}>
     */
    private function hours(): array
    {
        $admins = Counts::admins();
        $start = now()->utc()->startOfHour()->subHours(23);
        // Counted by the database, hour by hour (timestamps are UTC), never row by row here.
        $hour = "date_format(created_at, '%Y-%m-%dT%H:00:00Z')";
        $bucket = static fn ($query): array => $query->where('created_at', '>=', $start)->groupByRaw($hour)->selectRaw($hour.' as h, count(*) as n')->pluck('n', 'h')->map(fn (mixed $n): int => (int) $n)->all();
        $takes = $bucket(Take::query()->toBase()->whereNotIn(DB::raw('coalesce(recorded_by, user_id)'), $admins));
        $scores = $bucket(Score::query()->toBase()->whereNotIn('user_id', $admins));
        $signUps = $bucket(User::query()->toBase()->where('is_admin', false));
        $hours = [];

        for ($i = 0; $i < 24; $i++) {
            $hour = $start->copy()->addHours($i)->toIso8601ZuluString();
            $hours[] = ['hour' => $hour, 'takes' => $takes[$hour] ?? 0, 'scores' => $scores[$hour] ?? 0, 'signUps' => $signUps[$hour] ?? 0];
        }

        return $hours;
    }

    /**
     * @return list<array{kind: string, guest: int, account: int}>
     */
    private function features(): array
    {
        $rows = DB::table('usage_counts')->whereIn('kind', self::FEATURES)->where('day', '>', ReportDay::now()->subDays(30)->toDateString())
            ->groupBy('kind', 'audience')->selectRaw('kind, audience, sum(count) as n')->get();

        return array_map(static fn (string $kind): array => [
            'kind' => $kind,
            'guest' => (int) $rows->where('kind', $kind)->where('audience', '!=', 'account')->sum('n'),
            'account' => (int) $rows->where('kind', $kind)->where('audience', 'account')->sum('n'),
        ], self::FEATURES);
    }

    /**
     * @return array{freeAccounts: int, projectsAtLimit: int, projectsNearLimit: int, projectLimit: int}
     */
    private function limits(): array
    {
        $limit = (int) config('usage.plans.free.projects');
        $free = User::query()->where('is_admin', false)->whereNot(PlusQuery::apply(...));
        $counts = DB::table('projects')->whereNull('binned_at')->whereIn('user_id', (clone $free)->select('id'))
            ->groupBy('user_id')->selectRaw('user_id, count(*) as n')->pluck('n')->map(fn (mixed $n): int => (int) $n);

        return [
            'freeAccounts' => (clone $free)->count(),
            'projectsAtLimit' => $counts->filter(fn (int $n): bool => $n >= $limit)->count(),
            'projectsNearLimit' => $counts->filter(fn (int $n): bool => $n === $limit - 1)->count(),
            'projectLimit' => $limit,
        ];
    }

    /**
     * @param  list<float>  $values
     */
    private static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $mid = intdiv(count($values), 2);

        return round(count($values) % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2, 1);
    }
}
