<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Like with like (docs/growth-compare-plan.md; Hillel, 2026-11-09: "if I check it 10 am n day view it
 * should compare against the data for 10am yesterday", and "proroate all groups"): every period and
 * what it's compared with start at a midnight and end at the same wall-clock time, so each holds whole
 * days and one day cut at that time. The maths only, no database: the windows (section 1), a day's part
 * up to the cut (5.3), a usual day's shape (5.4) and how a change is judged (4.6).
 */
final class FairCompare
{
    public const PERIODS = ['today', '7d', '28d', 'month', '12w'];

    /** The old `?days=` choices, as periods. */
    public const DAYS = [1 => 'today', 7 => '7d', 30 => '28d', 84 => '12w'];

    /** Under this, on either side, a change is too few to tell. */
    public const FEW = 5;

    /** From this expected number up, a change is a percentage; under it, a count. */
    public const PERCENT_FROM = 20;

    /** A shape counts a day's weight for this much: shrinks a small sample toward a flat day. */
    private const SHRINK = 100;

    /**
     * The period and what it's compared with, as dates in the report zone. A window is its whole days
     * (`full`, inclusive, or null) and the one day cut at `cut` (`partial`, or null); at the month
     * edge, last month whole, scaled by `scale`.
     *
     * @return array{period: array{key: string, from: string, to: string, cut: string}, current: array<string, mixed>, previous: array<string, mixed>, usual: list<array<string, mixed>>}
     */
    public static function windows(string $period, CarbonImmutable $now, string $zone, float $dayShare = 0.0): array
    {
        $local = $now->setTimezone($zone);
        $today = $local->startOfDay();
        $cut = $local->format('H:i');
        $day = static fn (int $ago): string => $today->subDays($ago)->toDateString();
        $window = static fn (?int $fullFrom, ?int $fullTo, int $partial): array => [
            'full' => $fullFrom !== null && $fullTo !== null && $fullFrom >= $fullTo ? [$day($fullFrom), $day($fullTo)] : null,
            'partial' => $day($partial),
            'scale' => null,
            'from' => $day($fullFrom ?? $partial),
            'to' => $day($partial),
        ];

        $span = match ($period) {
            'today' => 1,
            '7d' => 7,
            '28d' => 28,
            '12w' => 84,
            'month' => null,
            default => throw new InvalidArgumentException("No period {$period}."),
        };

        if ($span !== null) {
            $whole = static fn (int $end): array => $window($span > 1 ? $end + $span - 1 : null, $span > 1 ? $end + 1 : null, $end);
            $usual = in_array($period, ['today', '7d'], true) ? array_map(static fn (int $j): array => $whole(7 * $j), [1, 2, 3, 4]) : [];

            return [
                'period' => ['key' => $period, 'from' => $day($span - 1), 'to' => $day(0), 'cut' => $cut],
                'current' => $whole(0),
                'previous' => $whole($span),
                'usual' => $usual,
            ];
        }

        // This month: the 1st to today at the cut, against last month the 1st to the same day.
        $n = $local->day;
        $first = $today->startOfMonth();
        $last = $first->subMonthNoOverflow();
        $length = $last->daysInMonth;
        $current = ['full' => $n > 1 ? [$first->toDateString(), $today->subDay()->toDateString()] : null, 'partial' => $today->toDateString(), 'scale' => null, 'from' => $first->toDateString(), 'to' => $today->toDateString()];

        if ($n <= $length) {
            $cutDay = $last->addDays($n - 1);
            $previous = ['full' => $n > 1 ? [$last->toDateString(), $cutDay->subDay()->toDateString()] : null, 'partial' => $cutDay->toDateString(), 'scale' => null, 'from' => $last->toDateString(), 'to' => $cutDay->toDateString()];
        } else {
            // Beyond last month's length (March 30 against February): all of it, scaled to this period's days.
            $days = ($n - 1) + $dayShare;
            $previous = ['full' => [$last->toDateString(), $last->endOfMonth()->toDateString()], 'partial' => null, 'scale' => $days / $length, 'scaledDays' => round($days, 1), 'from' => $last->toDateString(), 'to' => $last->endOfMonth()->toDateString()];
        }

        return ['period' => ['key' => 'month', 'from' => $first->toDateString(), 'to' => $today->toDateString(), 'cut' => $cut], 'current' => $current, 'previous' => $previous, 'usual' => []];
    }

    /**
     * Wall-clock `cut` (HH:MM) on `date` in `zone`, as a moment. On a spring-forward day a time in the
     * missing hour is the gap's end (01:30 in London on that day is 02:00); on the autumn day a
     * repeated time is its first pass.
     */
    public static function cutMoment(string $date, string $cut, string $zone): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $cut));
        $moment = CarbonImmutable::parse($date, $zone)->setTime($h, $m);

        if ($moment->format('H:i') !== sprintf('%02d:%02d', $h, $m) && $moment->format('Y-m-d') === $date) {
            $moment = $moment->setTime($moment->hour, 0);
        }

        return $moment;
    }

    /**
     * A day's count up to its cut (5.3). Exact from its hours when they cover the day: the day's total
     * shared out by the hours before the cut (the cut's own hour by its minutes), so a backfilled day
     * whose hours sum short still reads its whole total. Otherwise, the day's total times the usual
     * share by then.
     *
     * @param  array<string, int>  $hours  the day's UTC hours ('Y-m-d H:00:00') => count
     * @return array{value: float, estimated: bool}
     */
    public static function partial(int $total, array $hours, CarbonImmutable $cut, bool $covered, float $share): array
    {
        if ($total <= 0) {
            return ['value' => 0.0, 'estimated' => false];
        }

        $all = array_sum($hours);

        if (! $covered || $all <= 0) {
            return ['value' => $total * $share, 'estimated' => true];
        }

        $utc = $cut->utc();
        $hourStart = $utc->startOfHour();
        $before = 0.0;

        foreach ($hours as $hour => $n) {
            $at = CarbonImmutable::parse($hour, 'UTC');

            if ($at->lt($hourStart)) {
                $before += $n;
            } elseif ($at->eq($hourStart)) {
                $before += $n * $utc->minute / 60;
            }
        }

        return ['value' => $total * $before / $all, 'estimated' => false];
    }

    /**
     * The share of a usual day's count in by wall-clock `minutes` since midnight (5.4), from each local
     * hour's weight over the reference days: smoothed around the clock, an empty hour lifted a little
     * for each week of data, and a small sample drawn toward a flat day.
     *
     * @param  list<int|float>  $weights  24 local hours' totals
     */
    public static function shape(array $weights, int $weeks, int $minutes): float
    {
        $w = array_values(array_pad(array_slice($weights, 0, 24), 24, 0));
        $n = array_sum($w);
        $smooth = [];

        for ($h = 0; $h < 24; $h++) {
            $smooth[$h] = ($w[($h + 23) % 24] + 2 * $w[$h] + $w[($h + 1) % 24]) / 4 + 0.5 * max(0, $weeks);
        }

        $hour = intdiv(max(0, min(1440, $minutes)), 60);
        $sum = array_sum($smooth);
        $flat = max(0, min(1440, $minutes)) / 1440;

        if ($sum <= 0 || $hour >= 24) {
            return $hour >= 24 ? 1.0 : $flat;
        }

        $by = array_sum(array_slice($smooth, 0, $hour)) + $smooth[$hour] * ($minutes % 60) / 60;
        $lambda = $n / ($n + self::SHRINK);

        return $lambda * ($by / $sum) + (1 - $lambda) * $flat;
    }

    /**
     * How a change is said (4.6): `n` now against `expected` if nothing changed, where the baseline's
     * own noise is `k` times its size (1 for one window, 1/4 for a mean of four, the day's share for
     * an estimate from a day total) and an estimate counts a quarter more uncertain.
     *
     * @return array{verdict: string, z: float|null, change: array{kind: string, value: int|float}|null, low?: int, high?: int}
     */
    public static function judge(int $n, float $expected, float $k, bool $estimated, bool $range = false): array
    {
        $e = max(0.0, $expected);
        $sigma = sqrt($e * (1 + $k) * ($estimated ? 1.25 : 1.0));
        $bounds = $range ? ['low' => (int) max(0, round($e - 2 * $sigma)), 'high' => (int) round($e + 2 * $sigma)] : [];

        if ($n === 0 && $e < 0.5) {
            return ['verdict' => 'none', 'z' => null, 'change' => null, ...$bounds];
        }

        if (max($n, $e) < self::FEW) {
            return ['verdict' => 'tooFew', 'z' => null, 'change' => null, ...$bounds];
        }

        if ($e < 1) {
            return ['verdict' => 'new', 'z' => null, 'change' => null, ...$bounds];
        }

        $z = round(($n - $e) / $sigma, 2);
        $verdict = match (true) {
            $z >= 2 => 'up',
            $z <= -2 => 'down',
            $z >= 1.5 => 'maybeUp',
            $z <= -1.5 => 'maybeDown',
            default => 'same',
        };
        $change = match (true) {
            $e < self::PERCENT_FROM => ['kind' => 'count', 'value' => (int) round($n - $e)],
            $n > 3 * $e => ['kind' => 'times', 'value' => round($n / $e * 2) / 2],
            default => ['kind' => 'percent', 'value' => (int) round(($n - $e) / $e * 100)],
        };

        return ['verdict' => $verdict, 'z' => $z, 'change' => $change, ...$bounds];
    }

    /** A projection widens its range by this for the shape being a guess (as an estimate's verdict). */
    private const PROJECTION_WIDEN = 1.25;

    /**
     * Today's total from what's in so far (11.3): `n` over the usual share `s` done by now, with its range.
     * Hidden before a quarter of a usual day is in, under 15 so far, or with no shape yet.
     *
     * @return array{shown: bool, hidden: string|null, value: int|null, low: int|null, high: int|null}
     */
    public static function projectDay(int $n, float $s, bool $history = true): array
    {
        return self::project($n, $s, $history, $s < 0.25, 15, 1.0);
    }

    /**
     * The month's total (11.3): `n` so far over the `elapsed` days of its `length` (days before today plus
     * today's usual share). Hidden before the 4th (3 days in) or under 30 so far.
     *
     * @return array{shown: bool, hidden: string|null, value: int|null, low: int|null, high: int|null, rate: float|null}
     */
    public static function projectMonth(int $n, float $elapsed, int $length, bool $history = true): array
    {
        $projection = self::project($n, $elapsed / $length, $history, $elapsed < 3, 30, 1.0);

        return [...$projection, 'rate' => $projection['value'] !== null ? round($projection['value'] / $length, 1) : null];
    }

    /**
     * This week's total (11.3): `n` so far over `w`, the usual share of a Monday-to-Sunday week done.
     * Hidden before a quarter of a usual week is in, under 15 so far, or under 2 whole weeks of counts.
     *
     * @return array{shown: bool, hidden: string|null, value: int|null, low: int|null, high: int|null}
     */
    public static function projectWeek(int $n, float $w, bool $history = true): array
    {
        return self::project($n, $w, $history, $w < 0.25, 15, 1.0);
    }

    /**
     * A usual week's share by weekday (Monday first), from the last whole weeks' counts by weekday, drawn
     * toward a flat week while the sample is small.
     *
     * @param  list<int|float>  $byWeekday  7 totals, Monday to Sunday, over the reference weeks
     * @return list<float>
     */
    public static function weekShares(array $byWeekday): array
    {
        $w = array_values(array_pad(array_slice($byWeekday, 0, 7), 7, 0));
        $n = array_sum($w);
        $lambda = $n / ($n + 200);

        return array_map(static fn (int|float $d): float => $lambda * ($n > 0 ? $d / $n : 0) + (1 - $lambda) / 7, $w);
    }

    /**
     * The share of a usual week done: the weekdays already over, and today's share of today.
     *
     * @param  list<float>  $shares  weekShares()
     */
    public static function weekDone(array $shares, int $weekday, float $todayShare): float
    {
        return array_sum(array_slice($shares, 0, $weekday)) + ($shares[$weekday] ?? 0) * $todayShare;
    }

    /**
     * When a usual day reaches `share` (HH:MM, on the next whole hour): when an early projection shows.
     *
     * @param  list<int|float>  $weights
     */
    public static function reaches(array $weights, int $weeks, float $share): ?string
    {
        for ($minutes = 60; $minutes <= 1440; $minutes += 60) {
            if (self::shape($weights, $weeks, $minutes) >= $share) {
                return sprintf('%02d:00', intdiv($minutes, 60) % 24);
            }
        }

        return null;
    }

    /**
     * @return array{shown: bool, hidden: string|null, value: int|null, low: int|null, high: int|null}
     */
    private static function project(int $n, float $share, bool $history, bool $early, int $few, float $scale): array
    {
        $hidden = match (true) {
            ! $history => 'noHistory',
            $early => 'early',
            $n < $few => 'tooFew',
            default => null,
        };

        if ($hidden !== null || $share <= 0) {
            return ['shown' => false, 'hidden' => $hidden ?? 'early', 'value' => null, 'low' => null, 'high' => null];
        }

        $value = $n / $share * $scale;
        $range = 2 * sqrt($n * self::PROJECTION_WIDEN) / $share * $scale;

        return ['shown' => true, 'hidden' => null, 'value' => (int) round($value), 'low' => (int) max($n, round($value - $range)), 'high' => (int) round($value + $range)];
    }
}
