<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Actions\Counts\Funnel;
use App\Actions\Counts\Growth;
use App\Actions\Counts\GrowthCompare;
use App\Models\DropoffReport;
use App\Support\Stats\ReportDay;

/**
 * A Growth page's numbers as CSV (admin.growth.export; Hillel, 2026-11-23: "please enable Saul to export data as CSV"):
 * exactly the anonymous totals the page shows, from the same code that builds the page, one table a page, nothing more
 * (no person, no per-country count). UTF-8, a header row, ISO dates; a text cell that would start a spreadsheet formula
 * gets a "'" before it (a source tag comes from a link anyone can make).
 */
final class GrowthCsv
{
    public const PAGES = ['growth', 'funnel', 'dropoffs', 'sources', 'choirs'];

    /**
     * @param  array{period?: string|null, days?: int|null, experiment?: string|null}  $params
     * @return array{filename: string, csv: string, rows: int, from: string, to: string}
     */
    public function build(string $page, array $params): array
    {
        ['from' => $from, 'to' => $to, 'header' => $header, 'rows' => $rows] = match ($page) {
            'growth' => $this->growth((string) ($params['period'] ?? 'today')),
            'funnel' => $this->funnel(isset($params['experiment']) ? (string) $params['experiment'] : null, (int) ($params['days'] ?? 30)),
            'dropoffs' => $this->dropoffs(),
            'sources' => $this->sources((int) ($params['days'] ?? 7)),
            default => $this->choirs((string) ($params['period'] ?? '30d')),
        };

        $lines = array_map(self::line(...), [$header, ...$rows]);

        return [
            'filename' => "stackapella-growth-{$page}-{$from}_{$to}.csv",
            'csv' => implode("\n", $lines)."\n",
            'rows' => count($rows),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * The overview's chart: a row a slot (hour, day or week), each metric now and the period before.
     *
     * @return array{from: string, to: string, header: list<string>, rows: list<list<mixed>>}
     */
    private function growth(string $period): array
    {
        $shown = app(GrowthCompare::class)->show($period);
        $chart = (array) ($shown['chart'] ?? []);
        $metrics = array_filter((array) ($chart['metrics'] ?? []), 'is_array');
        $header = ['slot'];

        foreach (array_keys($metrics) as $metric) {
            $header[] = self::snake((string) $metric).'_now';
            $header[] = self::snake((string) $metric).'_before';
        }

        $rows = [];

        foreach (array_values((array) ($chart['slots'] ?? [])) as $i => $slot) {
            $row = [$slot];

            foreach ($metrics as $series) {
                $row[] = ((array) ($series['now'] ?? []))[$i] ?? null;
                $row[] = ((array) ($series['previous'] ?? []))[$i] ?? null;
            }

            $rows[] = $row;
        }

        $range = (array) ($shown['period'] ?? []);

        return ['from' => (string) ($range['from'] ?? ReportDay::today()), 'to' => (string) ($range['to'] ?? ReportDay::today()), 'header' => $header, 'rows' => $rows];
    }

    /**
     * The funnel: each step by arm (one arm, "all", without an experiment).
     *
     * @return array{from: string, to: string, header: list<string>, rows: list<list<mixed>>}
     */
    private function funnel(?string $experiment, int $days): array
    {
        $report = app(Funnel::class)->report($experiment, $days);
        $rows = [];

        foreach ((array) ($report['steps'] ?? []) as $step) {
            foreach ((array) ($step['arms'] ?? []) as $arm) {
                $rows[] = [$step['kind'] ?? null, $arm['arm'] ?? null, $arm['guest'] ?? null, $arm['account'] ?? null, $arm['total'] ?? null, $arm['rate'] ?? null];
            }
        }

        return ['from' => (string) ($report['from'] ?? ReportDay::today()), 'to' => (string) ($report['to'] ?? ReportDay::today()), 'header' => ['step', 'arm', 'guest', 'account', 'total', 'rate'], 'rows' => $rows];
    }

    /**
     * The latest week's drop-offs: each path's steps, as the page lists them.
     *
     * @return array{from: string, to: string, header: list<string>, rows: list<list<mixed>>}
     */
    private function dropoffs(): array
    {
        $latest = (array) (DropoffReport::query()->orderByDesc('week_end')->first()->data ?? []);
        $rows = [];

        foreach ((array) ($latest['paths'] ?? []) as $path) {
            foreach ((array) ($path['steps'] ?? []) as $step) {
                $rows[] = [$path['label'] ?? $path['path'] ?? null, $step['label'] ?? $step['step'] ?? null, $step['entered'] ?? null, $step['passed'] ?? null,
                    $step['passThrough'] ?? null, $step['before'] ?? null, $step['tooFew'] ?? null, $step['drop'] ?? null, $step['lost'] ?? null];
            }
        }

        return [
            'from' => (string) ($latest['weekStart'] ?? ReportDay::today()), 'to' => (string) ($latest['weekEnd'] ?? ReportDay::today()),
            'header' => ['path', 'step', 'entered', 'passed', 'pass_through', 'before', 'too_few', 'drop', 'lost'], 'rows' => $rows,
        ];
    }

    /**
     * The sources: a row a source, the page's columns.
     *
     * @return array{from: string, to: string, header: list<string>, rows: list<list<mixed>>}
     */
    private function sources(int $days): array
    {
        $shown = app(Growth::class)->sources($days);

        // The page's columns; its small sources are already folded into one "small" row, as the page shows them.
        return ['from' => (string) $shown['from'], 'to' => (string) $shown['to'], ...self::table((array) $shown['sources'], ['source', 'visits', 'arrivals', 'lesson1', 'courseLesson', 'signedUp', 'day2', 'week1'])];
    }

    /**
     * AI choirs: a row a day, as Growth's AI choirs card.
     *
     * @return array{from: string, to: string, header: list<string>, rows: list<list<mixed>>}
     */
    private function choirs(string $period): array
    {
        $shown = app(ChoirGrowth::class)->build($period === '7d' ? '7d' : '30d');

        return ['from' => (string) $shown['from'], 'to' => (string) $shown['to'], ...self::table((array) $shown['days'], ['date', 'made', 'free', 'plus', 'comped', 'together', 'partMinutes', 'failedOurs', 'failedTake'])];
    }

    /**
     * Rows as a table of the page's columns, in its order (a header even with no rows).
     *
     * @param  array<mixed>  $rows
     * @param  list<string>  $keys
     * @return array{header: list<string>, rows: list<list<mixed>>}
     */
    private static function table(array $rows, array $keys): array
    {
        return [
            'header' => array_map(self::snake(...), $keys),
            'rows' => array_values(array_map(static fn (mixed $row): array => array_map(static fn (string $key): mixed => ((array) $row)[$key] ?? null, $keys), $rows)),
        ];
    }

    /**
     * @param  list<mixed>  $cells
     */
    private static function line(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells));
    }

    private static function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => (string) (is_scalar($value) ? $value : ''),
        };

        // A text cell a spreadsheet would read as a formula is kept as text.
        if (! is_int($value) && ! is_float($value) && preg_match('/^[=+\-@\t\r]/', $text) === 1) {
            $text = "'".$text;
        }

        return preg_match('/[",\r\n]/', $text) === 1 ? '"'.str_replace('"', '""', $text).'"' : $text;
    }

    private static function snake(string $key): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }
}
