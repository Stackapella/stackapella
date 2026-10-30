<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use Illuminate\Support\Facades\DB;

/**
 * Admin > Growth (docs/admin-growth-plan.md): the Overview's tiles and steps, and where people came
 * from. Read from the same anonymous daily totals as Usage and the Funnel: numbers per day, never a
 * person.
 */
final class Growth
{
    /** The Overview's tiles: each kind's count today, yesterday and the same weekday last week. */
    private const TILES = ['arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'signedUp' => ['sign_up'], 'plusStarted' => ['plus_started']];

    /** "How far they got", landing visit to Plus. */
    private const STEPS = ['visits' => ['site_visit'], 'start' => ['f_site_start'], 'arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'unit1' => ['f_unit1'], 'signedUp' => ['sign_up'], 'plusStarted' => ['plus_started']];

    /** Sources' columns; signedUp is the device's own first sign-up here, which carries its source. */
    private const COLUMNS = ['visits' => ['site_visit'], 'arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'signedUp' => ['f_account'], 'day2' => ['f_day2'], 'week1' => ['f_week1']];

    /** A source with fewer arrivals than this is folded into one row, so a tiny one never stands for a person. */
    public const FOLD_UNDER = 5;

    /**
     * admin.growth.show: the tiles (today, yesterday and the same weekday last week, UTC) and each
     * step's total over the last `$days` days.
     *
     * @return array{date: string, from: string, to: string, tiles: list<array{key: string, today: int, yesterday: int, lastWeek: int}>, steps: list<array{key: string, total: int}>}
     */
    public function overview(int $days): array
    {
        $today = now()->utc()->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $kinds = array_values(array_unique(array_merge(...array_values(self::TILES), ...array_values(self::STEPS))));
        $since = min($from, $today->copy()->subDays(7))->toDateString();

        /** @var array<string, array<string, int>> $byDay [date][kind] => total */
        $byDay = [];

        foreach (DB::table('usage_counts')->whereIn('kind', $kinds)->where('day', '>=', $since)->selectRaw('day, kind, sum(count) as total')->groupBy('day', 'kind')->get() as $row) {
            $byDay[(string) $row->day][(string) $row->kind] = (int) $row->total;
        }

        $on = static fn (string $date, array $of): int => array_sum(array_map(static fn (string $kind): int => $byDay[$date][$kind] ?? 0, $of));
        $tiles = [];

        foreach (self::TILES as $key => $of) {
            $tiles[] = ['key' => $key, 'today' => $on($today->toDateString(), $of), 'yesterday' => $on($today->copy()->subDay()->toDateString(), $of), 'lastWeek' => $on($today->copy()->subDays(7)->toDateString(), $of)];
        }

        $dates = array_filter(array_keys($byDay), static fn (string $date): bool => $date >= $from->toDateString());
        $steps = [];

        foreach (self::STEPS as $key => $of) {
            $steps[] = ['key' => $key, 'total' => array_sum(array_map(static fn (string $date): int => $on($date, $of), $dates))];
        }

        return ['date' => $today->toDateString(), 'from' => $from->toDateString(), 'to' => $today->toDateString(), 'tiles' => $tiles, 'steps' => $steps];
    }

    /**
     * admin.sources.show: each source's visits and funnel steps over the last `$days` days, most
     * arrivals first, with those under FOLD_UNDER arrivals folded into one "small" row, last.
     *
     * @return array{from: string, to: string, sources: list<array<string, int|string>>}
     */
    public function sources(int $days): array
    {
        $today = now()->utc()->startOfDay();
        $from = $today->copy()->subDays($days - 1)->toDateString();
        $kinds = array_values(array_unique(array_merge(...array_values(self::COLUMNS))));

        /** @var array<string, array<string, int>> $bySource [source][kind] => total */
        $bySource = [];

        foreach (DB::table('usage_counts')->whereIn('kind', $kinds)->where('day', '>=', $from)->selectRaw('source, kind, sum(count) as total')->groupBy('source', 'kind')->get() as $row) {
            $bySource[(string) $row->source][(string) $row->kind] = (int) $row->total;
        }

        $rows = [];

        foreach ($bySource as $source => $totals) {
            $sum = static fn (string $key): int => array_sum(array_map(static fn (string $kind): int => $totals[$kind] ?? 0, self::COLUMNS[$key]));
            $rows[] = ['source' => $source === '' ? 'none' : (string) $source, 'visits' => $sum('visits'), 'arrivals' => $sum('arrivals'), 'lesson1' => $sum('lesson1'), 'signedUp' => $sum('signedUp'), 'day2' => $sum('day2'), 'week1' => $sum('week1')];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['arrivals'], $a['source']] <=> [$a['arrivals'], $b['source']]);
        $shown = array_values(array_filter($rows, static fn (array $row): bool => $row['arrivals'] >= self::FOLD_UNDER));
        $small = array_values(array_filter($rows, static fn (array $row): bool => $row['arrivals'] < self::FOLD_UNDER));

        if ($small !== []) {
            $folded = ['source' => 'small', 'sources' => count($small), 'visits' => 0, 'arrivals' => 0, 'lesson1' => 0, 'signedUp' => 0, 'day2' => 0, 'week1' => 0];

            foreach ($small as $row) {
                foreach (['visits', 'arrivals', 'lesson1', 'signedUp', 'day2', 'week1'] as $key) {
                    $folded[$key] += $row[$key];
                }
            }

            $shown[] = $folded;
        }

        return ['from' => $from, 'to' => $today->toDateString(), 'sources' => $shown];
    }
}
