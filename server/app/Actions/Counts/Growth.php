<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Growth (docs/admin-growth-plan.md): where people came from (the Overview is GrowthCompare's). Read from the same anonymous daily totals as Usage and the Funnel: numbers per day, never a
 * person.
 */
final class Growth
{
    /** Sources' columns; signedUp is the device's own first sign-up here, which carries its source. */
    private const COLUMNS = ['visits' => ['site_visit'], 'arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'courseLesson' => ['f_course_lesson'], 'signedUp' => ['f_account'], 'day2' => ['f_day2'], 'week1' => ['f_week1']];

    /** A source with fewer arrivals than this is folded into one row, so a tiny one never stands for a person. */
    public const FOLD_UNDER = 5;

    /** Under this many, a tile's change is noise ("+18%" from 11 to 13): the Overview says "few so far". */
    public const LOW_SAMPLE = 20;

    /**
     * admin.sources.show: each source's visits and funnel steps over the last `$days` days, most
     * arrivals first, with those under FOLD_UNDER arrivals folded into one "small" row, last.
     *
     * @return array{from: string, to: string, sources: list<array<string, int|string>>}
     */
    public function sources(int $days): array
    {
        $today = ReportDay::now()->startOfDay();
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
            $rows[] = ['source' => $source === '' ? 'none' : (string) $source, 'visits' => $sum('visits'), 'arrivals' => $sum('arrivals'), 'lesson1' => $sum('lesson1'), 'courseLesson' => $sum('courseLesson'), 'signedUp' => $sum('signedUp'), 'day2' => $sum('day2'), 'week1' => $sum('week1')];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['arrivals'], $a['source']] <=> [$a['arrivals'], $b['source']]);
        $shown = array_values(array_filter($rows, static fn (array $row): bool => $row['arrivals'] >= self::FOLD_UNDER));
        $small = array_values(array_filter($rows, static fn (array $row): bool => $row['arrivals'] < self::FOLD_UNDER));

        if ($small !== []) {
            $folded = ['source' => 'small', 'sources' => count($small), 'visits' => 0, 'arrivals' => 0, 'lesson1' => 0, 'courseLesson' => 0, 'signedUp' => 0, 'day2' => 0, 'week1' => 0];

            foreach ($small as $row) {
                foreach (['visits', 'arrivals', 'lesson1', 'courseLesson', 'signedUp', 'day2', 'week1'] as $key) {
                    $folded[$key] += $row[$key];
                }
            }

            $shown[] = $folded;
        }

        return ['from' => $from, 'to' => $today->toDateString(), 'sources' => $shown];
    }
}
