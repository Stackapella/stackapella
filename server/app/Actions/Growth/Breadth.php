<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Actions\Counts\Funnel;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\DB;

/**
 * How much of the app singers use (docs/retention-plan.md, "Breadth"; Hillel, 2026-11-11: "a goal should be to get
 * people to use more"). Only from the two anonymous counts: br:<n>, once a device a day beside app_day (how many of
 * the core features it has tried), and ft:<feature>, a device's first use of each. For the week to today and the
 * four before it: the active device-days at each level, guests and accounts apart, and the share at three or more;
 * and this week's first uses of each feature in the list's order, beside the new devices. The accounts' tried sets
 * are never read here: they only fill a singer's devices (the coordinator, 2026-11-11). Daily totals only.
 */
final class Breadth
{
    /** The weeks read: this one and the four before it, so the headline moves on last week and four weeks ago. */
    private const WEEKS = 5;

    /** The level the headline counts from: three or more of the features tried. */
    private const HEADLINE_LEVEL = 3;

    /**
     * @return array{from: string, to: string, floor: int, headline: array<string, mixed>, weeks: list<array<string, mixed>>, newDevices: int, features: list<array<string, mixed>>}
     */
    public function build(): array
    {
        $to = ReportDay::now()->startOfDay();
        $from = $to->copy()->subDays(6);
        $start = $from->copy()->subDays(7 * (self::WEEKS - 1));
        $floor = (int) config('counts.dropoffs.floor', 30);
        $features = array_values(array_map('strval', (array) config('counts.features')));
        $top = count($features);

        // Each week's start (oldest first), and the week each day falls in.
        $starts = array_map(static fn (int $i): string => $start->copy()->addDays(7 * $i)->toDateString(), range(0, self::WEEKS - 1));
        $weekOf = static fn (string $day): int => intdiv((int) $start->diffInDays($day), 7);

        // The device-days at each level, a week at a time, for guests and for accounts.
        $guests = array_fill(0, self::WEEKS, array_fill(0, $top + 1, 0));
        $accounts = $guests;
        $firsts = array_fill(0, self::WEEKS, array_fill_keys($features, 0));
        $arrivals = array_fill(0, self::WEEKS, 0);

        $rows = DB::table('usage_counts')->whereBetween('day', [$start->toDateString(), $to->toDateString()])
            ->where(static fn ($q) => $q->where('kind', 'like', 'br:%')->orWhere('kind', 'like', 'ft:%')->orWhereIn('kind', Funnel::ARRIVALS))
            ->selectRaw('day, kind, audience, sum(count) as n')->groupBy('day', 'kind', 'audience')->get();

        foreach ($rows as $row) {
            $week = $weekOf(substr((string) $row->day, 0, 10));
            $kind = (string) $row->kind;
            $n = (int) $row->n;
            if (str_starts_with($kind, 'br:')) {
                $level = (int) substr($kind, 3);
                if ($level >= 0 && $level <= $top) {
                    if ((string) $row->audience === 'account') {
                        $accounts[$week][$level] += $n;
                    } else {
                        $guests[$week][$level] += $n;
                    }
                }
            } elseif (str_starts_with($kind, 'ft:')) {
                $feature = substr($kind, 3);
                if (isset($firsts[$week][$feature])) {
                    $firsts[$week][$feature] += $n;
                }
            } else {
                $arrivals[$week] += $n;
            }
        }

        $weeks = [];
        foreach ($starts as $i => $weekStart) {
            $all = array_map(static fn (int $g, int $a): int => $g + $a, $guests[$i], $accounts[$i]);
            $weeks[] = [
                'from' => $weekStart,
                'to' => $start->copy()->addDays(7 * $i + 6)->toDateString(),
                'all' => self::mix($all, $floor),
                'guest' => self::mix($guests[$i], $floor),
                'account' => self::mix($accounts[$i], $floor),
            ];
        }

        $now = self::WEEKS - 1;
        $current = $weeks[$now]['all'];
        $newDevices = $arrivals[$now];
        $out = [];
        $previous = null;
        foreach ($features as $feature) {
            $n = $firsts[$now][$feature];
            $out[] = [
                'feature' => $feature,
                'firstUses' => $n,
                'lastWeek' => $firsts[$now - 1][$feature],
                // The share of this week's new devices, and the pass-through from the feature before it on the ladder.
                'perNew' => $newDevices > 0 ? round($n / $newDevices, 3) : null,
                'fromPrevious' => $previous !== null && $previous > 0 ? round($n / $previous, 3) : null,
                // Too few when what it's read against (the feature before it; the first, the new devices) is under the floor.
                'tooFew' => ($previous ?? $newDevices) < $floor,
            ];
            $previous = $n;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'floor' => $floor,
            'headline' => [
                'deviceDays' => $current['deviceDays'],
                'threePlus' => $current['threePlus'],
                'mean' => $current['mean'],
                'tooFew' => $current['tooFew'],
                'lastWeek' => $weeks[$now - 1]['all']['threePlus'],
                'fourWeeksAgo' => $weeks[0]['all']['threePlus'],
            ],
            'weeks' => $weeks,
            'newDevices' => $newDevices,
            'features' => $out,
        ];
    }

    /**
     * One week's active device-days at each level (0 to the number of features), the share at three or more and
     * the mean level; too few under the floor.
     *
     * @param  array<int, int>  $levels  device-days by level, from 0
     * @return array{deviceDays: int, levels: list<int>, threePlus: float|null, mean: float|null, tooFew: bool}
     */
    private static function mix(array $levels, int $floor): array
    {
        $total = array_sum($levels);
        $weighted = array_sum(array_map(static fn (int $n, int $level): int => $n * $level, $levels, array_keys($levels)));

        return [
            'deviceDays' => $total,
            'levels' => array_values($levels),
            'threePlus' => $total > 0 ? round(array_sum(array_slice($levels, self::HEADLINE_LEVEL)) / $total, 3) : null,
            'mean' => $total > 0 ? round($weighted / $total, 2) : null,
            'tooFew' => $total < $floor,
        ];
    }
}
