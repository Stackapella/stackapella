<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * People using the app (docs/admin-analytics-plan.md; Hillel: "people using the app broken down
 * into signed in vs anonymous"), from the anonymous daily counts only, split by the batch's
 * guest or account. We keep no IDs, so:
 * - `opens` is app_open, every load of the app, the only number with history;
 * - `devices` is app_day, each device once a UTC day, counted only since the app sent it;
 * - `reach` is app_week, each device once an ISO week (UTC): real weekly reach, from its first
 *   whole week.
 * A week's opens and devices are the average of its finished days that were counted, leaving out a
 * kind's first day (the deploy came mid-day); the last week is the one yesterday is in, so far.
 *
 * @phpstan-type Split array{guest: int|float, account: int|float}
 */
final class Reach
{
    private const KINDS = ['app_open', 'app_day', 'app_week'];

    /**
     * @return array{
     *     days: list<array{day: string, opens: Split, devices: Split|null}>,
     *     today: array{day: string, opens: Split, devices: Split|null},
     *     weeks: list<array{from: string, to: string, days: int, opens: Split, devices: Split|null, reach: Split|null}>,
     *     previous: list<array{from: string, to: string, days: int, opens: Split, devices: Split|null, reach: Split|null}>
     * }
     */
    public function show(): array
    {
        // 24 ISO weeks, the last the one yesterday is in (so far): the counts may be younger than
        // any whole week, and a chart of finished weeks alone would show nothing.
        $last = ReportDay::now()->subDay()->startOfDay();
        $first = $last->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(23);
        $yesterday = $last->toDateString();

        $rows = DB::table('usage_counts')->whereIn('kind', self::KINDS)->where('day', '>=', $first->toDateString())->where('day', '<=', $yesterday)
            ->groupBy('day', 'kind', 'audience')->selectRaw('day, kind, audience, sum(count) as n')->get();
        $counts = [];

        foreach ($rows as $row) {
            $who = $row->audience === 'account' ? 'account' : 'guest';
            $day = substr((string) $row->day, 0, 10);
            $counts[(string) $row->kind][$day] ??= ['guest' => 0, 'account' => 0];
            $counts[(string) $row->kind][$day][$who] += (int) $row->n;
        }

        // The first day each kind was ever counted (it may be partial: the deploy came mid-day).
        $since = DB::table('usage_counts')->whereIn('kind', self::KINDS)->groupBy('kind')->selectRaw('kind, min(day) as day')->pluck('day', 'kind')
            ->map(fn (mixed $day): string => substr((string) $day, 0, 10));
        $opensSince = $since->get('app_open');
        $daySince = $since->get('app_day');
        $weekSince = $since->get('app_week');

        $days = [];

        for ($day = $first->copy(); $day->toDateString() <= $yesterday; $day->addDay()) {
            $d = $day->toDateString();
            $days[] = [
                'day' => $d,
                'opens' => $counts['app_open'][$d] ?? ['guest' => 0, 'account' => 0],
                'devices' => $daySince !== null && $d >= $daySince ? ($counts['app_day'][$d] ?? ['guest' => 0, 'account' => 0]) : null,
            ];
        }

        $weeks = [];

        for ($i = 0; $i < 24; $i++) {
            $from = $first->copy()->addWeeks($i);
            $to = $from->copy()->addDays(6);
            $end = min($to->toDateString(), $yesterday);
            $in = fn (string $kind, ?string $after = null): Collection => collect($counts[$kind] ?? [])
                ->filter(fn (array $n, string $d): bool => $d >= $from->toDateString() && $d <= $end && ($after === null || $d > $after));
            $opens = self::counted($from, $end, $opensSince);
            $devices = self::counted($from, $end, $daySince);
            $weeks[] = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                // Its finished days: 7, or fewer for the week yesterday is in.
                'days' => (int) $from->copy()->startOfDay()->diffInDays(Carbon::parse($end, $from->getTimezone())->startOfDay()) + 1,
                // The average of the days its kind was counted (after a first day that may be partial).
                'opens' => self::average($in('app_open', $opensSince), $opens),
                'devices' => $devices > 0 ? self::average($in('app_day', $daySince), $devices) : null,
                // Only a week counted from its Monday; the week yesterday is in, so far.
                'reach' => $weekSince !== null && $from->toDateString() > $weekSince ? self::sum($in('app_week')) : null,
            ];
        }

        // Today so far (UTC), for Growth's Today: not in `days`, which are finished.
        $now = ReportDay::today();
        $todays = DB::table('usage_counts')->whereIn('kind', ['app_open', 'app_day'])->where('day', $now)
            ->groupBy('kind', 'audience')->selectRaw('kind, audience, sum(count) as n')->get();
        $split = fn (string $kind): array => [
            'guest' => (int) $todays->where('kind', $kind)->where('audience', '!=', 'account')->sum('n'),
            'account' => (int) $todays->where('kind', $kind)->where('audience', 'account')->sum('n'),
        ];
        $today = ['day' => $now, 'opens' => $split('app_open'), 'devices' => $daySince !== null ? $split('app_day') : null];

        return ['days' => $days, 'weeks' => array_slice($weeks, 12), 'previous' => array_slice($weeks, 0, 12), 'today' => $today];
    }

    /**
     * @param  Collection<string, array{guest: int, account: int}>  $days
     * @return array{guest: float, account: float}
     */
    private static function average(Collection $days, int $over): array
    {
        $sum = self::sum($days);

        return $over > 0 ? ['guest' => round($sum['guest'] / $over, 1), 'account' => round($sum['account'] / $over, 1)] : ['guest' => 0.0, 'account' => 0.0];
    }

    /** How many of the days `$from` to `$end` come after a kind's first day (none if never counted). */
    private static function counted(Carbon $from, string $end, ?string $since): int
    {
        if ($since === null) {
            return 0;
        }

        $start = max($from->toDateString(), Carbon::parse($since)->addDay()->toDateString());

        return $start > $end ? 0 : (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }

    /**
     * @param  Collection<string, array{guest: int, account: int}>  $days
     * @return array{guest: int, account: int}
     */
    private static function sum(Collection $days): array
    {
        return ['guest' => (int) $days->sum('guest'), 'account' => (int) $days->sum('account')];
    }
}
