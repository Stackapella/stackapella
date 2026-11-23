<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Support\OwnChoir\ChoirCounts;
use App\Support\Stats\GrowthStart;
use App\Support\Stats\ReportDay;

/**
 * AI choirs on Admin > Growth (admin.growth.choirs; Hillel, 2026-11-23: "make sure to include AI choirs made on admin
 * growth"): each day's songs made by plan, friends' parts made, the part-minutes the renderer took on, failures (ours,
 * the take) and recordings started to songs made, over 7 or 30 days beside the period before. Anonymous daily counts
 * only (App\Support\OwnChoir\ChoirCounts), from the growth fresh start.
 */
final class ChoirGrowth
{
    /** Each number and the kinds it sums. */
    private const METRICS = [
        'made' => ['choir_made_free', 'choir_made_plus', 'choir_made_comped'],
        'free' => ['choir_made_free'],
        'plus' => ['choir_made_plus'],
        'comped' => ['choir_made_comped'],
        'together' => ['own_choir_friend_ready'],
        'partMinutes' => ['choir_part_seconds'],
        'failedOurs' => ['choir_failed_ours'],
        'failedTake' => ['choir_failed_take'],
    ];

    private const STARTED = 'choir_take_started';

    /**
     * @return array<string, mixed>
     */
    public function build(string $period): array
    {
        $days = $period === '7d' ? 7 : 30;
        $to = ReportDay::now()->startOfDay();
        $from = $to->copy()->subDays($days - 1);
        $beforeTo = $from->copy()->subDay();
        $beforeFrom = $beforeTo->copy()->subDays($days - 1);

        $now = $this->read($from->toDateString(), $to->toDateString());
        $before = $this->read($beforeFrom->toDateString(), $beforeTo->toDateString());
        $sum = static fn (array $byKind, string $metric): int => array_sum(array_map(static fn (string $k): int => $byKind[$k] ?? 0, self::METRICS[$metric]));
        $value = static fn (array $byKind, string $metric): int|float => $metric === 'partMinutes' ? round($sum($byKind, $metric) / 60, 1) : $sum($byKind, $metric);

        $list = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            $list[] = ['date' => $date, ...array_map(static fn (string $metric): int|float => $value($now['days'][$date] ?? [], $metric), array_combine(array_keys(self::METRICS), array_keys(self::METRICS)))];
        }

        $totals = [];

        foreach (array_keys(self::METRICS) as $metric) {
            $totals[$metric] = ['n' => $value($now['total'], $metric), 'before' => $value($before['total'], $metric)];
        }

        $rate = static fn (int $made, int $started): ?float => $started > 0 ? round($made / $started, 3) : null;
        $started = ['n' => $now['total'][self::STARTED] ?? 0, 'before' => $before['total'][self::STARTED] ?? 0];
        $made = ['n' => $sum($now['total'], 'made'), 'before' => $sum($before['total'], 'made')];

        return [
            'period' => $days === 7 ? '7d' : '30d',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => ReportDay::zone(),
            'floor' => (int) config('counts.dropoffs.floor', 30),
            'days' => $list,
            'totals' => $totals,
            'funnel' => [
                'captureStarted' => $started,
                'made' => $made,
                'rate' => $rate($made['n'], $started['n']),
                'rateBefore' => $rate($made['before'], $started['before']),
            ],
        ];
    }

    /**
     * The kinds' counts by day and in total, from..to (report days).
     *
     * @return array{days: array<string, array<string, int>>, total: array<string, int>}
     */
    private function read(string $from, string $to): array
    {
        $kinds = [...array_merge(...array_values(self::METRICS)), self::STARTED];
        $out = ['days' => [], 'total' => []];

        foreach (app(GrowthStart::class)->counts()->whereBetween('day', [$from, $to])->whereIn('kind', $kinds)
            ->selectRaw('day, kind, sum(count) as n')->groupBy('day', 'kind')->get() as $row) {
            $day = substr((string) $row->day, 0, 10);
            $kind = (string) $row->kind;
            $out['days'][$day][$kind] = ($out['days'][$day][$kind] ?? 0) + (int) $row->n;
            $out['total'][$kind] = ($out['total'][$kind] ?? 0) + (int) $row->n;
        }

        return $out;
    }

    /**
     * Every kind it reads, for a check that each is counted (ChoirCounts and config counts).
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_values(array_unique([...array_merge(...array_values(self::METRICS)), self::STARTED, ...ChoirCounts::GROWTH]));
    }
}
