<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use App\Support\Stats\Counts;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\DB;

/**
 * Active singers over time (docs/admin-analytics-plan.md, slice 1): Counts::activeSingers() for the
 * 7 and 30 days ending each day, kept once a day in `active_singer_days` so old weeks are never
 * worked out again. The first run fills the last 24 weeks from takes, scores and lessons.
 */
final class ActiveSingers
{
    /** How far back a missing day is filled: the chart's 12 weeks and the 12 before. */
    public const DAYS = 24 * 7;

    /**
     * Works out every finished day (UTC, up to yesterday) in the window that has no row yet.
     *
     * @return int how many days were filled
     */
    public function fill(): int
    {
        $last = ReportDay::now()->subDay()->startOfDay();
        $first = $last->copy()->subDays(self::DAYS - 1);
        $have = DB::table('active_singer_days')->where('day', '>=', $first->toDateString())->pluck('day')
            ->map(fn (mixed $day): string => substr((string) $day, 0, 10))->flip();
        $filled = 0;

        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            if ($have->has($day->toDateString())) {
                continue;
            }

            $end = $day->copy()->endOfDay();
            DB::table('active_singer_days')->insertOrIgnore([
                'day' => $day->toDateString(),
                'week' => Counts::activeSingers($end->copy()->subDays(7), $end),
                'month' => Counts::activeSingers($end->copy()->subDays(30), $end),
            ]);
            $filled++;
        }

        return $filled;
    }

    /**
     * admin.active.show: today's 7 and 30 days, and the chart's points, each the 7 days ending a
     * week apart from yesterday: `weeks` the last 12, `previous` the 12 before, oldest first.
     *
     * @return array{week: int, month: int, weeks: list<array{to: string, week: int|null, month: int|null}>, previous: list<array{to: string, week: int|null, month: int|null}>}
     */
    public function show(): array
    {
        $last = ReportDay::now()->subDay()->startOfDay();
        $days = DB::table('active_singer_days')->where('day', '>=', $last->copy()->subDays(self::DAYS)->toDateString())->get(['day', 'week', 'month'])
            ->keyBy(fn (object $row): string => substr((string) $row->day, 0, 10));
        $points = [];

        for ($i = 23; $i >= 0; $i--) {
            $to = $last->copy()->subDays(7 * $i)->toDateString();
            $row = $days->get($to);
            $points[] = ['to' => $to, 'week' => $row !== null ? (int) $row->week : null, 'month' => $row !== null ? (int) $row->month : null];
        }

        return [
            'week' => Counts::activeSingers(now()->subDays(7)),
            'month' => Counts::activeSingers(now()->subDays(30)),
            'weeks' => array_slice($points, 12),
            'previous' => array_slice($points, 0, 12),
        ];
    }
}
