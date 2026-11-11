<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use App\Models\Experiment;
use App\Support\Stats\ReportDay;
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

    /**
     * A device drawn into an experiment's arm, when the screen that differs first shows: an arm's
     * denominator, as arrivals are onb1's (counted only in experiment_counts, with `variants`).
     */
    public const ENROLLED = 'exp_enrol';

    /** The experiment auth.config's `experiment` (one, drawn at a device's first open) still carries. */
    public const LEGACY = 'onb1';

    /** Every experiment, cached a few minutes and forgotten whenever Admin changes one. */
    public const CACHE = 'experiments.all';

    /** Enrolled per arm before the guardrail check may stop a test (fewer, and it's too noisy to say). */
    public const GUARD_MIN_PER_ARM = 50;

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
        foreach (self::registry() as $experiment) {
            foreach ($experiment->armNames() as $arm) {
                if ($variant === $experiment->key.'-'.$arm) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The registry (Admin, Experiments), oldest first.
     *
     * @return list<Experiment>
     */
    public static function registry(): array
    {
        // The rows, not the models: the cache keeps plain data.
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(self::CACHE, 300, static fn (): array => Experiment::query()->orderBy('id')->get()->map(static fn (Experiment $e): array => $e->getAttributes())->all());

        return array_values(Experiment::hydrate($rows)->all());
    }

    /**
     * The kinds beyond the funnel that a running experiment measures (its metric and guardrails: any counted kind,
     * a lesson's step for one), so counts keep the arm on exactly those and no more rows than needed.
     *
     * @return list<string>
     */
    public static function measured(): array
    {
        $kinds = [];

        foreach (self::registry() as $experiment) {
            if (in_array($experiment->status(), [Experiment::RUNNING, Experiment::READING], true)) {
                $kinds = [...$kinds, $experiment->metric, ...$experiment->guardrails];
            }
        }

        return array_values(array_diff(array_unique($kinds), self::kinds(), [self::ENROLLED]));
    }

    /**
     * auth.config's `experiments`: every one running (enrolling, or read later but its arms still
     * shown), for the app's experiment(id): who may join, and how.
     *
     * @return list<array{id: string, arms: list<array{arm: string, weight: int}>, enrol: bool, audience: array<string, bool|string>, excludes: list<string>}>
     */
    public function running(): array
    {
        if (config('counts.enabled') !== true) {
            return [];
        }

        $today = ReportDay::today();
        $running = [];

        foreach (self::registry() as $experiment) {
            $status = $experiment->status();
            $readFrom = $experiment->readFrom();

            if ($experiment->starts_on !== null && $experiment->starts_on->toDateString() <= $today && in_array($status, [Experiment::RUNNING, Experiment::READING], true) && ($readFrom === null || $today <= $readFrom)) {
                $running[] = ['id' => $experiment->key, 'arms' => array_values($experiment->arms), 'enrol' => $experiment->enrolling(), 'audience' => (array) $experiment->audience, 'excludes' => array_values($experiment->excludes)];
            }
        }

        return $running;
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
                $today = ReportDay::today();

                // Only onb1: the app draws it at a device's first open; any other is in `experiments`.
                if ($experiment['id'] === self::LEGACY && $experiment['start'] !== null && $experiment['start'] <= $today && ($experiment['readFrom'] === null || $today <= $experiment['readFrom'])) {
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
        $list = [];

        foreach (array_reverse(self::registry()) as $experiment) {
            $list[] = [
                'id' => $experiment->key,
                'arms' => $experiment->armNames(),
                'start' => $experiment->starts_on?->toDateString(),
                'end' => $experiment->ends_on?->toDateString(),
                'enrolUntil' => $experiment->enrolUntil(),
                'readFrom' => $experiment->readFrom(),
                'plannedPerArm' => $experiment->planned_per_arm,
                'primary' => $experiment->metric,
                'guardrail' => $experiment->guardrails[0] ?? null,
                'enrolling' => $experiment->enrolling(),
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
        // onb1's arms are on usage_counts (`variant`), counted from arrival; every other experiment's
        // are in experiment_counts, counted from enrolment (exp_enrol).
        $registry = $experiment !== null && $experiment['id'] !== self::LEGACY;
        $arrivalKinds = $registry ? [self::ENROLLED] : self::ARRIVALS;
        // A registry experiment may measure any counted kind (a lesson's step, a share): read beside the funnel.
        $model = $registry ? collect(self::registry())->firstWhere('key', $experiment['id']) : null;
        $extra = $model instanceof Experiment ? array_values(array_diff(array_unique([$model->metric, ...$model->guardrails]), self::kinds(), [self::ENROLLED])) : [];
        $query = DB::table($registry ? 'experiment_counts' : 'usage_counts')->whereIn('kind', [...$arrivalKinds, ...self::kinds(), ...$extra]);

        if ($experiment !== null) {
            $arms = $experiment['arms'];
            $query->whereIn('variant', array_map(static fn (string $arm): string => "{$experiment['id']}-{$arm}", $arms));
            $armOf = static fn (\stdClass $row): string => substr((string) $row->variant, strlen($experiment['id']) + 1);
        } else {
            $arms = ['all'];
            $query->where('day', '>=', ReportDay::now()->subDays($days - 1)->toDateString());
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
        $arrivals = array_combine($arms, array_map(static fn (string $arm): int => array_sum(array_map(static fn (string $kind): int => $count($kind, $arm), $arrivalKinds)), $arms));
        $rate = static fn (int $n, int $of): ?float => $of > 0 ? round($n / $of, 4) : null;

        $steps = [];

        foreach ([...($registry ? [self::ENROLLED] : []), ...(array) config('counts.funnel'), ...$extra] as $kind) {
            $steps[] = [
                'kind' => $kind,
                'arms' => array_map(static fn (string $arm): array => [
                    'arm' => $arm,
                    'guest' => $totals[$kind][$arm]['guest'] ?? 0,
                    'account' => $totals[$kind][$arm]['account'] ?? 0,
                    'total' => $count($kind, $arm),
                    'rate' => $rate($count($kind, $arm), $arrivals[$arm]),
                ], $arms),
                'difference' => count($arms) === 2 && ! in_array($kind, $arrivalKinds, true)
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
            'from' => $experiment !== null ? ($dates[0] ?? $experiment['start']) : ReportDay::now()->subDays($days - 1)->toDateString(),
            'to' => $experiment !== null ? ($dates[count($dates) - 1] ?? $experiment['start']) : ReportDay::today(),
            'split' => $experiment !== null && count($arms) === 2 ? self::split($arms, $arrivals) : null,
            'enough' => $experiment !== null && $experiment['plannedPerArm'] > 0 && $arrivals !== [] && min($arrivals) >= $experiment['plannedPerArm'],
            'steps' => $steps,
            'asks' => $asks,
            'days' => $dayList,
        ];
    }

    /**
     * A two-arm registry experiment's guardrails where B is worse than A beyond chance at 99% (the
     * plan's one early stop; docs/experiments-plan.md), once each arm has enough enrolled to say.
     *
     * @return list<array{kind: string, points: float, low: float, high: float}>
     */
    public function guardrailsWorse(Experiment $experiment): array
    {
        if ($experiment->key === self::LEGACY || count($experiment->arms) !== 2) {
            return [];
        }

        $report = $this->report($experiment->key, 30);
        $arrivals = array_map(static fn (array $arm): int => (int) $arm['arrivals'], (array) ($report['split']['arms'] ?? []));

        if (count($arrivals) !== 2 || min($arrivals) < self::GUARD_MIN_PER_ARM) {
            return [];
        }

        $worse = [];

        foreach ($experiment->guardrails as $kind) {
            $step = collect((array) $report['steps'])->firstWhere('kind', $kind);
            $difference = is_array($step) ? self::difference((int) $step['arms'][0]['total'], $arrivals[0], (int) $step['arms'][1]['total'], $arrivals[1], 2.576) : null;

            if ($difference !== null && $difference['high'] < 0) {
                $worse[] = ['kind' => $kind, ...$difference];
            }
        }

        return $worse;
    }

    /**
     * The second arm's rate minus the first's, in percentage points, with its 95% interval (a
     * two-proportion normal interval). Null until both arms have arrivals.
     *
     * @return array{points: float, low: float, high: float}|null
     */
    public static function difference(int $a, int $ofA, int $b, int $ofB, float $z = 1.96): ?array
    {
        if ($ofA < 1 || $ofB < 1) {
            return null;
        }

        // Daily totals can put a step a little over its arrivals; a rate never passes 1.
        $pa = min(1.0, $a / $ofA);
        $pb = min(1.0, $b / $ofB);
        $se = sqrt($pa * (1 - $pa) / $ofA + $pb * (1 - $pb) / $ofB);
        $d = $pb - $pa;

        return ['points' => round($d * 100, 2), 'low' => round(($d - $z * $se) * 100, 2), 'high' => round(($d + $z * $se) * 100, 2)];
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
