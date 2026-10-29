<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The onboarding funnel (docs/onboarding-review.md, section 4): each step is a device's first,
 * counted once per device by the app and kept only as a daily total per kind, guest or account,
 * and experiment arm (config/counts.php). Nothing here follows anyone: conversions are ratios of
 * totals, read over an experiment's whole window.
 */
final class Funnel
{
    /** The arrival kinds: a device's first open, sent as one or the other. */
    public const ARRIVALS = ['f_arrive', 'f_arrive_start'];

    /** A split this unlikely under 50/50 is a bug, not a result (a sample ratio mismatch). */
    private const SPLIT_P = 0.001;

    /**
     * Every funnel and ask kind counts.create takes a variant on.
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        $asks = [];

        foreach ((array) config('counts.asks') as $ask) {
            $asks[] = "ask_{$ask}_shown";
            $asks[] = "ask_{$ask}_yes";
        }

        return [...array_values((array) config('counts.funnel')), ...$asks];
    }

    /**
     * Whether a variant ("onb1-b") names a configured experiment's arm.
     */
    public static function knownVariant(string $variant): bool
    {
        foreach ((array) config('counts.experiments') as $experiment) {
            foreach ((array) $experiment['arms'] as $arm) {
                if ($variant === $experiment['id'].'-'.$arm) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * auth.config's `experiment`: the one running, and whether new devices still join it, else null.
     * Cached a few minutes (enrolment ends on a count).
     *
     * @return array{id: string, arms: list<string>, enrol: bool}|null
     */
    public function current(): ?array
    {
        if (config('counts.enabled') !== true) {
            return null;
        }

        /** @var array{id: string, arms: list<string>, enrol: bool}|false $current */
        $current = Cache::remember('funnel.current', 300, function (): array|false {
            foreach ($this->experiments() as $experiment) {
                $today = now()->toDateString();

                if ($experiment['start'] !== null && $experiment['start'] <= $today && ($experiment['readFrom'] === null || $today <= $experiment['readFrom'])) {
                    return ['id' => $experiment['id'], 'arms' => $experiment['arms'], 'enrol' => $experiment['enrolling']];
                }
            }

            return false;
        });

        return $current === false ? null : $current;
    }

    /**
     * The configured experiments, newest first, each with when enrolment stopped (its end day
     * passed) and when it can be read (a week on, so day-2 and week-1 returns have matured). Only
     * the date stops it, never the counts: anyone can send counts, so a flood of arrivals must not
     * be able to end a test (admin.funnel.show's `enough` says when the planned sample is in, for
     * Hillel to set the end).
     *
     * @return list<array{id: string, arms: list<string>, start: string|null, end: string|null, enrolUntil: string|null, readFrom: string|null, plannedPerArm: int, primary: string, guardrail: string|null, enrolling: bool}>
     */
    public function experiments(): array
    {
        $today = now()->toDateString();
        $list = [];

        foreach (array_reverse((array) config('counts.experiments')) as $experiment) {
            $arms = array_values(array_map('strval', (array) $experiment['arms']));
            $start = $experiment['start'] ?? null;
            $end = $experiment['end'] ?? null;
            $planned = (int) ($experiment['plannedPerArm'] ?? 0);
            $until = $start !== null && $end !== null && $end < $today ? $end : null;

            $list[] = [
                'id' => (string) $experiment['id'],
                'arms' => $arms,
                'start' => $start,
                'end' => $end,
                'enrolUntil' => $until,
                'readFrom' => $until !== null ? CarbonImmutable::parse($until)->addDays(7)->toDateString() : null,
                'plannedPerArm' => $planned,
                'primary' => (string) $experiment['primary'],
                'guardrail' => $experiment['guardrail'] ?? null,
                'enrolling' => $start !== null && $start <= $today && $until === null,
            ];
        }

        return $list;
    }

    /**
     * admin.funnel.show: an experiment's arms (or every device over the last `$days` days, as one
     * arm "all"), each step as a share of that arm's arrivals, the asks taken, the 50/50 check and
     * the difference between two arms with its 95% interval.
     *
     * @return array<string, mixed>
     */
    public function report(?string $experimentId, int $days): array
    {
        $experiments = $this->experiments();
        $experiment = collect($experiments)->firstWhere('id', $experimentId);
        $query = DB::table('usage_counts')->whereIn('kind', [...self::ARRIVALS, ...self::kinds()]);

        if ($experiment !== null) {
            $arms = $experiment['arms'];
            $query->whereIn('variant', array_map(static fn (string $arm): string => "{$experiment['id']}-{$arm}", $arms));
            $armOf = static fn (\stdClass $row): string => substr((string) $row->variant, strlen($experiment['id']) + 1);
        } else {
            $arms = ['all'];
            $query->where('day', '>=', now()->subDays($days - 1)->toDateString());
            $armOf = static fn (\stdClass $row): string => 'all';
        }

        $rows = $query->selectRaw('day, kind, variant, audience, sum(count) as total')->groupBy('day', 'kind', 'variant', 'audience')->orderBy('day')->get();

        // [kind][arm][audience] => total, and [date][kind][arm] => total.
        $totals = [];
        $byDay = [];

        foreach ($rows as $row) {
            $arm = $armOf($row);
            $kind = (string) $row->kind;
            $totals[$kind][$arm][(string) $row->audience] = ($totals[$kind][$arm][(string) $row->audience] ?? 0) + (int) $row->total;
            $byDay[(string) $row->day][$kind][$arm] = ($byDay[(string) $row->day][$kind][$arm] ?? 0) + (int) $row->total;
        }

        $count = static fn (string $kind, string $arm): int => array_sum($totals[$kind][$arm] ?? []);
        $arrivals = array_combine($arms, array_map(static fn (string $arm): int => array_sum(array_map(static fn (string $kind): int => $count($kind, $arm), self::ARRIVALS)), $arms));
        $rate = static fn (int $n, int $of): ?float => $of > 0 ? round($n / $of, 4) : null;

        $steps = [];

        foreach ((array) config('counts.funnel') as $kind) {
            $steps[] = [
                'kind' => $kind,
                'arms' => array_map(static fn (string $arm): array => [
                    'arm' => $arm,
                    'guest' => $totals[$kind][$arm]['guest'] ?? 0,
                    'account' => $totals[$kind][$arm]['account'] ?? 0,
                    'total' => $count($kind, $arm),
                    'rate' => $rate($count($kind, $arm), $arrivals[$arm]),
                ], $arms),
                'difference' => count($arms) === 2 && ! in_array($kind, self::ARRIVALS, true)
                    ? self::difference($count($kind, $arms[0]), $arrivals[$arms[0]], $count($kind, $arms[1]), $arrivals[$arms[1]])
                    : null,
            ];
        }

        $asks = array_map(static fn (string $ask): array => [
            'ask' => $ask,
            'arms' => array_map(static fn (string $arm): array => [
                'arm' => $arm,
                'shown' => $count("ask_{$ask}_shown", $arm),
                'yes' => $count("ask_{$ask}_yes", $arm),
                'rate' => $rate($count("ask_{$ask}_yes", $arm), $count("ask_{$ask}_shown", $arm)),
            ], $arms),
        ], array_values((array) config('counts.asks')));

        $dayList = [];

        foreach ($byDay as $date => $kinds) {
            foreach ($kinds as $kind => $perArm) {
                foreach ($perArm as $arm => $total) {
                    $dayList[] = ['date' => $date, 'kind' => $kind, 'arm' => $arm, 'total' => $total];
                }
            }
        }

        $dates = array_keys($byDay);

        return [
            'experiments' => $experiments,
            'experiment' => $experiment['id'] ?? null,
            'from' => $experiment !== null ? ($dates[0] ?? $experiment['start']) : now()->subDays($days - 1)->toDateString(),
            'to' => $experiment !== null ? ($dates[count($dates) - 1] ?? $experiment['start']) : now()->toDateString(),
            'split' => $experiment !== null && count($arms) === 2 ? self::split($arms, $arrivals) : null,
            'enough' => $experiment !== null && $experiment['plannedPerArm'] > 0 && $arrivals !== [] && min($arrivals) >= $experiment['plannedPerArm'],
            'steps' => $steps,
            'asks' => $asks,
            'days' => $dayList,
        ];
    }

    /**
     * The second arm's rate minus the first's, in percentage points, with its 95% interval (a
     * two-proportion normal interval). Null until both arms have arrivals.
     *
     * @return array{points: float, low: float, high: float}|null
     */
    private static function difference(int $a, int $ofA, int $b, int $ofB): ?array
    {
        if ($ofA < 1 || $ofB < 1) {
            return null;
        }

        // Daily totals can put a step a little over its arrivals; a rate never passes 1.
        $pa = min(1.0, $a / $ofA);
        $pb = min(1.0, $b / $ofB);
        $se = sqrt($pa * (1 - $pa) / $ofA + $pb * (1 - $pb) / $ofB);
        $d = $pb - $pa;

        return ['points' => round($d * 100, 2), 'low' => round(($d - 1.96 * $se) * 100, 2), 'high' => round(($d + 1.96 * $se) * 100, 2)];
    }

    /**
     * Each arm's share of arrivals, and how likely a split this far from 50/50 is by chance.
     *
     * @param  list<string>  $arms
     * @param  array<string, int>  $arrivals
     * @return array{arms: list<array{arm: string, arrivals: int, share: float|null}>, pValue: float|null, ok: bool}
     */
    private static function split(array $arms, array $arrivals): array
    {
        $n = array_sum($arrivals);
        $p = null;

        if ($n > 0) {
            $z = abs($arrivals[$arms[0]] - $n / 2) / sqrt($n / 4);
            $p = round(self::erfc($z / M_SQRT2), 6);
        }

        return [
            'arms' => array_map(static fn (string $arm): array => ['arm' => $arm, 'arrivals' => $arrivals[$arm], 'share' => $n > 0 ? round($arrivals[$arm] / $n, 4) : null], $arms),
            'pValue' => $p,
            'ok' => $p === null || $p >= self::SPLIT_P,
        ];
    }

    /**
     * The complementary error function (Numerical Recipes' erfcc, good to about 1e-7).
     */
    private static function erfc(float $x): float
    {
        $t = 1 / (1 + 0.5 * abs($x));
        $y = $t * exp(-$x * $x - 1.26551223 + $t * (1.00002368 + $t * (0.37409196 + $t * (0.09678418 + $t * (-0.18628806 + $t * (0.27886807 + $t * (-1.13520398 + $t * (1.48851587 + $t * (-0.82215223 + $t * 0.17087277)))))))));

        return $x >= 0 ? $y : 2 - $y;
    }
}
