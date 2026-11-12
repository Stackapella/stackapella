<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use App\Models\Score;
use App\Models\Take;
use App\Models\User;
use App\Support\Ops\Releases;
use App\Support\Site\SiteVisit;
use App\Support\Stats\Counts;
use App\Support\Stats\FairCompare;
use App\Support\Stats\ReportDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * admin.growth.show (docs/growth-compare-plan.md, 5.5): each Growth number against the same period
 * before cut at the same clock time, and against a usual one, with a verdict the web only words. Read
 * from the anonymous daily counts and their hours (usage_count_hours): numbers, never a person.
 */
final class GrowthCompare
{
    /** The Overview's cards, in order: each its kinds (summed) and the family whose day shape it shares. */
    private const METRICS = [
        'visits' => ['kinds' => 'sv', 'family' => 'site'],
        'arrivals' => ['kinds' => Funnel::ARRIVALS, 'family' => 'app'],
        'lesson1' => ['kinds' => ['f_lesson1'], 'family' => 'app', 'rate' => 'arrivals'],
        'lessons' => ['kinds' => ['lesson_done'], 'family' => 'app'],
        'signUps' => ['kinds' => ['sign_up'], 'family' => 'accounts', 'rate' => 'arrivals'],
        'asks' => ['kinds' => 'askYes', 'family' => 'app', 'rate' => 'shown'],
        'day2' => ['kinds' => ['f_day2'], 'family' => 'app'],
        'plus' => ['kinds' => ['plus_started'], 'family' => 'accounts'],
    ];

    /** The account asks Growth sums (never the course ask's places, already in acct_course). */
    private const ASKS = ['account', 'acct_week', 'acct_songs', 'acct_unit', 'acct_risk', 'acct_settings', 'acct_course'];

    /** Counts the server can date from its own records, filled in by the hour before hourly counting began. */
    private const BACKFILLED = ['sign_up', 'plus_started'];

    /** The old Overview's tiles, kept for an older web: the same numbers, now cut at the same time. */
    private const TILES = ['arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'signedUp' => ['sign_up'], 'plusStarted' => ['plus_started']];

    private const STEPS = ['visits' => ['site_visit'], 'start' => ['f_site_start'], 'arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'unit1' => ['f_unit1'], 'signedUp' => ['sign_up'], 'plusStarted' => ['plus_started']];

    /** A rate is compared only when its base is at least this on both sides. */
    private const RATE_BASE = 30;

    /** firstLessons counts any course's first lesson once that count has this many days. */
    private const FIRST_LESSONS_DAYS = 35;

    /** @var array<string, array<string, int>> [date][kind] => total */
    private array $days = [];

    /** @var array<string, array<string, int>> [kind][UTC hour] => total */
    private array $hours = [];

    /** @var array<string, string> kind => its first counted day */
    private array $firsts = [];

    private ?CarbonImmutable $hourlySince = null;

    private CarbonImmutable $now;

    private string $zone;

    /** @var array<string, float> family => the usual share of a day in by now */
    private array $shares = [];

    /**
     * @return array<string, mixed>
     */
    public function show(string $period): array
    {
        $period = in_array($period, FairCompare::PERIODS, true) ? $period : 'today';
        $this->zone = ReportDay::zone();
        $this->now = CarbonImmutable::now()->setTimezone($this->zone);
        $minutes = $this->now->hour * 60 + $this->now->minute;

        $first = DB::table('usage_count_hours')->whereNotIn('kind', self::BACKFILLED)->min('hour');
        $this->hourlySince = $first !== null ? CarbonImmutable::parse((string) $first, 'UTC') : null;

        foreach ($this->weights() as $family => $weights) {
            $this->shares[$family] = FairCompare::shape($weights, 4, $minutes);
        }

        $windows = FairCompare::windows($period, $this->now, $this->zone, $this->shares['site']);
        $week = FairCompare::windows('7d', $this->now, $this->zone, $this->shares['site']);
        $shifted = FairCompare::windows($period, $this->now->subDays(7), $this->zone, $this->shares['site']);
        $this->load([...$this->spans($windows), ...$this->spans($week), $shifted['current']['from']], $windows, $week, $shifted);

        $metrics = [];

        foreach (self::METRICS as $key => $metric) {
            $metrics[] = $this->metric($key, $this->kinds($metric['kinds']), $metric['family'], $windows, $metric['rate'] ?? null);
        }

        $usualAvailable = max(0, ...array_map(static fn (array $m): int => (int) ($m['usual']['available'] ?? 0), $metrics));
        $firstLessons = $this->since(['f_course_lesson']) !== null && $this->since(['f_course_lesson']) <= $this->now->subDays(self::FIRST_LESSONS_DAYS)->toDateString();
        $tracking = [];

        foreach (['visits' => 'visits', 'arrivals' => 'arrivals', 'firstLessons' => $firstLessons ? 'f_course_lesson' : 'f_lesson1', 'signUps' => 'signUps'] as $key => $of) {
            $metric = $of === 'f_course_lesson' || $of === 'f_lesson1' ? ['kinds' => [$of], 'family' => 'app'] : self::METRICS[$of];
            $row = $this->metric($key, $this->kinds($metric['kinds']), $metric['family'], $week, $key === 'signUps' ? 'arrivals' : null, usualOnly: true);
            $tracking[] = $key === 'firstLessons' ? [...$row, 'measure' => $firstLessons ? 'anyCourse' : 'lesson1'] : $row;
        }

        $sections = [];

        foreach (SiteVisit::SECTIONS as $section) {
            $row = $this->metric('sv_'.$section, ['sv_'.$section], 'site', $week, null);
            $sections[] = ['key' => 'sv_'.$section, 'now' => $row['now'], 'previous' => $row['previous']];
        }

        $previous = $windows['previous'];

        return [
            'date' => $this->now->toDateString(),
            'from' => $windows['period']['from'],
            'to' => $windows['period']['to'],
            'tiles' => $this->tiles($windows, $shifted),
            'steps' => $this->steps($windows['period']['from']),
            'now' => $this->now->toIso8601String(),
            'period' => [...$windows['period'], 'dayShare' => round($this->shares['site'], 3), 'early' => $period === 'today' && $this->shares['site'] < 0.15],
            'comparisons' => [
                ['key' => 'previous', 'from' => $previous['from'], 'to' => $previous['to'], 'cut' => $windows['period']['cut'], 'scaledDays' => $previous['scaledDays'] ?? null],
                ['key' => 'usual', 'windows' => count($windows['usual']), 'available' => $usualAvailable],
            ],
            'hourlySince' => $this->hourlySince?->utc()->toIso8601ZuluString(),
            'countingSince' => $this->firsts === [] ? null : min($this->firsts),
            'metrics' => $metrics,
            'sections' => $sections,
            'tracking' => ['period' => $week['period'], 'metrics' => $tracking],
            'chart' => $this->chart($windows),
            'breaks' => $this->breaks(min([$windows['period']['from'], ...$this->spans($windows)])),
        ];
    }

    /**
     * One metric: its count this period, and each comparison judged.
     *
     * @param  list<string>  $kinds
     * @param  array<string, mixed>  $windows
     * @return array<string, mixed>
     */
    private function metric(string $key, array $kinds, string $family, array $windows, ?string $rate, bool $usualOnly = false): array
    {
        $since = $this->since($kinds);
        $now = (int) round($this->value($kinds, $windows['current'], $family, true)['value']);
        $today = $windows['period']['key'] === 'today';
        $row = ['key' => $key, 'now' => $now, 'since' => $since];

        if (! $usualOnly) {
            $row['previous'] = $this->previous($kinds, $family, $windows, $now, $since, $today);
        }

        $row['usual'] = $windows['usual'] === [] ? null : $this->usual($kinds, $family, $windows, $now, $since, $today);

        if ($rate !== null) {
            $base = $rate === 'shown' ? $this->kinds('askShown') : $this->kinds(Funnel::ARRIVALS);
            $row['rate'] = $this->rate($rate, $now, $kinds, $base, $family, $windows, $since);
        } elseif (! $usualOnly) {
            $row['rate'] = null;
        }

        return $row;
    }

    /**
     * @param  list<string>  $kinds
     * @param  array<string, mixed>  $windows
     * @return array<string, mixed>
     */
    private function previous(array $kinds, string $family, array $windows, int $now, ?string $since, bool $today): array
    {
        $window = $windows['previous'];

        if ($since === null || $window['from'] < $since) {
            return $this->noHistory($window['from'], $since);
        }

        $v = $this->value($kinds, $window, $family, false);
        $single = $today && $v['estimated'];
        $k = $single ? $this->shares[$family] : 1.0;
        $base = $single ? $this->total($kinds, (string) $window['partial']) : (int) round($v['value']);

        return ['expected' => round($v['value'], 1), 'base' => $base, 'k' => round($k, 3), 'estimated' => $v['estimated'], ...FairCompare::judge($now, $v['value'], $k, $v['estimated'])];
    }

    /**
     * @param  list<string>  $kinds
     * @param  array<string, mixed>  $windows
     * @return array<string, mixed>
     */
    private function usual(array $kinds, string $family, array $windows, int $now, ?string $since, bool $today): array
    {
        $usable = array_values(array_filter($windows['usual'], static fn (array $w): bool => $since !== null && $w['from'] >= $since));
        $available = count($usable);

        if ($available < 2) {
            return [...$this->noHistory($windows['usual'][1]['from'], $since), 'available' => $available];
        }

        $values = array_map(fn (array $w): array => $this->value($kinds, $w, $family, false), $usable);
        $sum = array_sum(array_column($values, 'value'));
        $estimated = in_array(true, array_column($values, 'estimated'), true);
        $single = $today && $estimated;
        $k = (1 / $available) * ($single ? $this->shares[$family] : 1.0);
        $base = $single ? array_sum(array_map(fn (array $w): int => $this->total($kinds, (string) $w['partial']), $usable)) : (int) round($sum);
        $expected = $sum / $available;

        return ['expected' => round($expected, 1), 'base' => $base, 'k' => round($k, 3), 'estimated' => $estimated, 'available' => $available, ...FairCompare::judge($now, $expected, $k, $estimated, true)];
    }

    /**
     * A share (of arrivals, of asks shown) now and in the period before, compared only on a big enough base.
     *
     * @param  list<string>  $kinds
     * @param  list<string>  $baseKinds
     * @param  array<string, mixed>  $windows
     * @return array<string, mixed>
     */
    private function rate(string $of, int $now, array $kinds, array $baseKinds, string $family, array $windows, ?string $since): array
    {
        $base = (int) round($this->value($baseKinds, $windows['current'], $family, true)['value']);
        $history = $since !== null && $windows['previous']['from'] >= $since;
        $before = $history ? $this->value($kinds, $windows['previous'], $family, false)['value'] : 0.0;
        $baseBefore = $history ? $this->value($baseKinds, $windows['previous'], $family, false)['value'] : 0.0;
        $comparable = $history && $base >= self::RATE_BASE && $baseBefore >= self::RATE_BASE;
        $verdict = null;

        if ($comparable) {
            $p1 = $now / $base;
            $p2 = $before / $baseBefore;
            $pooled = ($now + $before) / ($base + $baseBefore);
            $se = sqrt(max(1e-12, $pooled * (1 - $pooled) * (1 / $base + 1 / $baseBefore)));
            $z = ($p1 - $p2) / $se;
            $verdict = match (true) {
                $z >= 2 => 'up',
                $z <= -2 => 'down',
                $z >= 1.5 => 'maybeUp',
                $z <= -1.5 => 'maybeDown',
                default => 'same',
            };
        }

        return [
            'of' => $of, 'base' => $base, 'now' => $base > 0 ? round($now / $base, 4) : null,
            'baseBefore' => (int) round($baseBefore), 'before' => $baseBefore > 0 ? round($before / $baseBefore, 4) : null,
            'comparable' => $comparable, 'verdict' => $verdict,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noHistory(string $from, ?string $since): array
    {
        $gap = $since === null ? null : (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($since), false);

        return [
            'expected' => null, 'base' => null, 'k' => null, 'estimated' => false, 'verdict' => 'noHistory', 'z' => null, 'change' => null,
            'comparesFrom' => $gap === null ? null : CarbonImmutable::parse($this->now->toDateString())->addDays(max(0, $gap))->toDateString(),
        ];
    }

    /**
     * A window's count: its whole days and its one day cut at the time (today's own count is already so far).
     *
     * @param  list<string>  $kinds
     * @param  array<string, mixed>  $window
     * @return array{value: float, estimated: bool}
     */
    private function value(array $kinds, array $window, string $family, bool $current): array
    {
        $full = 0;

        if ($window['full'] !== null) {
            for ($d = CarbonImmutable::parse($window['full'][0]); $d->toDateString() <= $window['full'][1]; $d = $d->addDay()) {
                $full += $this->total($kinds, $d->toDateString());
            }
        }

        if ($window['scale'] !== null) {
            return ['value' => $full * (float) $window['scale'], 'estimated' => true];
        }

        $day = (string) $window['partial'];
        $total = $this->total($kinds, $day);

        if ($current) {
            return ['value' => (float) ($full + $total), 'estimated' => false];
        }

        $cut = FairCompare::cutMoment($day, $this->now->format('H:i'), $this->zone);
        $start = CarbonImmutable::parse($day, $this->zone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($day, $this->zone)->addDay()->startOfDay()->utc();
        $hours = [];

        foreach ($kinds as $kind) {
            foreach ($this->hours[$kind] ?? [] as $hour => $n) {
                $at = CarbonImmutable::parse($hour, 'UTC');

                if ($at->gte($start) && $at->lt($end)) {
                    $hours[$hour] = ($hours[$hour] ?? 0) + $n;
                }
            }
        }

        $covered = array_diff($kinds, self::BACKFILLED) === []
            ? $start->gte($this->now->utc()->subDays(100))
            : $this->hourlySince !== null && $start->gte($this->hourlySince);
        $part = FairCompare::partial($total, $hours, $cut, $covered, $this->shares[$family]);

        return ['value' => $full + $part['value'], 'estimated' => $part['estimated']];
    }

    /**
     * @param  list<string>  $kinds
     */
    private function total(array $kinds, string $day): int
    {
        return array_sum(array_map(fn (string $kind): int => $this->days[$day][$kind] ?? 0, $kinds));
    }

    /**
     * @param  list<string>  $kinds
     */
    private function since(array $kinds): ?string
    {
        $firsts = array_values(array_filter(array_map(fn (string $kind): ?string => $this->firsts[$kind] ?? null, $kinds)));

        return $firsts === [] ? null : min($firsts);
    }

    /**
     * @param  list<string>|string  $kinds
     * @return list<string>
     */
    private function kinds(array|string $kinds): array
    {
        return match ($kinds) {
            'sv' => array_map(static fn (string $s): string => 'sv_'.$s, SiteVisit::SECTIONS),
            'askYes' => array_map(static fn (string $a): string => "ask_{$a}_yes", self::ASKS),
            'askShown' => array_map(static fn (string $a): string => "ask_{$a}_shown", self::ASKS),
            default => array_values((array) $kinds),
        };
    }

    /**
     * Every first day the windows reach back to.
     *
     * @param  array<string, mixed>  $windows
     * @return list<string>
     */
    private function spans(array $windows): array
    {
        return array_values(array_map(static fn (array $w): string => (string) $w['from'], [$windows['current'], $windows['previous'], ...$windows['usual']]));
    }

    /**
     * The days, the hours of the cut days, each kind's first day and when hourly counting began: four reads.
     *
     * @param  list<string>  $froms
     * @param  array<string, mixed>  ...$sets
     */
    private function load(array $froms, array ...$sets): void
    {
        $kinds = array_values(array_unique([
            ...array_merge(...array_map(fn (array $m): array => $this->kinds($m['kinds']), array_values(self::METRICS))),
            ...$this->kinds('askShown'), 'f_course_lesson', ...array_merge(...array_values(self::TILES)), ...array_merge(...array_values(self::STEPS)),
        ]));
        $earliest = min([$this->now->toDateString(), ...$froms]);

        foreach (DB::table('usage_counts')->whereIn('kind', $kinds)->where('day', '>=', $earliest)->selectRaw('day, kind, sum(count) as total')->groupBy('day', 'kind')->get() as $row) {
            $this->days[substr((string) $row->day, 0, 10)][(string) $row->kind] = (int) $row->total;
        }

        foreach (DB::table('usage_counts')->whereIn('kind', $kinds)->selectRaw('kind, min(day) as first')->groupBy('kind')->get() as $row) {
            $this->firsts[(string) $row->kind] = substr((string) $row->first, 0, 10);
        }

        // The hours of every day a window is cut on (one read, within the 100 days kept).
        $cutDays = [];

        foreach ($sets as $set) {
            foreach ([$set['previous'], ...$set['usual']] as $w) {
                if ($w['partial'] !== null) {
                    $cutDays[] = (string) $w['partial'];
                }
            }
        }

        // Today and yesterday too, for the chart's hours.
        $cutDays[] = $this->now->toDateString();
        $cutDays[] = $this->now->subDay()->toDateString();

        $from = CarbonImmutable::parse(min($cutDays), $this->zone)->startOfDay()->utc()->format('Y-m-d H:i:s');
        $to = CarbonImmutable::parse(max($cutDays), $this->zone)->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s');

        foreach (DB::table('usage_count_hours')->whereIn('kind', $kinds)->where('hour', '>=', $from)->where('hour', '<', $to)->selectRaw('hour, kind, sum(count) as total')->groupBy('hour', 'kind')->get() as $row) {
            $this->hours[(string) $row->kind][substr((string) $row->hour, 0, 19)] = (int) $row->total;
        }
    }

    /**
     * Each family's count by local hour over the last 28 whole days (5.4): its own hours once 14 days of
     * them exist, before that the times our own records were made (takes, practice scores and accounts for
     * the site and the app, accounts alone for sign-ups and Plus). Cached an hour.
     *
     * @return array<string, list<int>>
     */
    private function weights(): array
    {
        $today = $this->now->startOfDay();
        $own = $this->hourlySince !== null && $this->hourlySince->lte($today->subDays(14)->utc());

        /** @var array<string, list<int>> */
        return Cache::remember('growth:shape:'.$this->zone.':'.$today->toDateString().':'.($own ? 'own' : 'proxy'), 3600, function () use ($today, $own): array {
            $from = $today->subDays(28)->utc()->format('Y-m-d H:i:s');
            $to = $today->utc()->format('Y-m-d H:i:s');
            $local = fn (array $rows): array => array_reduce(array_keys($rows), function (array $by, int|string $hour) use ($rows): array {
                $h = (int) CarbonImmutable::parse((string) $hour, 'UTC')->setTimezone($this->zone)->format('G');
                $by[$h] = ($by[$h] ?? 0) + (int) $rows[$hour];

                return $by;
            }, []);
            $sum = static fn (array ...$parts): array => array_map(static fn (int $h): int => array_sum(array_map(static fn (array $p): int => (int) ($p[$h] ?? 0), $parts)), range(0, 23));

            if ($own) {
                $families = [
                    'site' => ['site_visit', ...$this->kinds('sv')],
                    'app' => [...Funnel::ARRIVALS, 'f_lesson1', 'f_course_lesson', 'lesson_done', 'f_day2', ...$this->kinds('askShown'), ...$this->kinds('askYes')],
                    'accounts' => self::BACKFILLED,
                ];
                $byKind = [];

                foreach (DB::table('usage_count_hours')->whereIn('kind', array_merge(...array_values($families)))->where('hour', '>=', $from)->where('hour', '<', $to)->groupBy('hour', 'kind')->selectRaw('hour, kind, sum(count) as n')->get() as $row) {
                    $byKind[(string) $row->kind][substr((string) $row->hour, 0, 19)] = (int) $row->n;
                }

                return array_map(fn (array $kinds): array => $sum(...array_map(fn (string $kind): array => $local($byKind[$kind] ?? []), $kinds)), $families);
            }

            $admins = Counts::admins();
            $bucket = "date_format(created_at, '%Y-%m-%d %H:00:00')";
            $grouped = static fn (Builder $query): array => $query->where('created_at', '>=', $from)->where('created_at', '<', $to)->groupByRaw($bucket)->selectRaw($bucket.' as h, count(*) as n')->pluck('n', 'h')->all();
            $users = $local($grouped(User::query()->toBase()->where('is_admin', false)));
            $records = $sum($users, $local($grouped(Take::query()->toBase()->whereNotIn(DB::raw('coalesce(recorded_by, user_id)'), $admins))), $local($grouped(Score::query()->toBase()->whereNotIn('user_id', $admins))));

            return ['site' => $records, 'app' => $records, 'accounts' => $sum($users)];
        });
    }

    /**
     * The chart for the period (docs/growth-compare-plan.md, 11): Today by local hour, 7 days, 4 weeks and
     * the month by day, 12 weeks by Monday-to-Sunday week. Visits and Arrivals each with the comparison's
     * count per slot and a projection (or why it's hidden), and sign-ups as a strip. Projections are the
     * server's, never a verdict.
     *
     * @param  array<string, mixed>  $windows
     * @return array<string, mixed>
     */
    private function chart(array $windows): array
    {
        $period = (string) $windows['period']['key'];
        $kind = match ($period) {
            'today' => 'hours',
            '12w' => 'weeks',
            default => 'days',
        };
        $today = $this->now->toDateString();
        $minutes = $this->now->hour * 60 + $this->now->minute;
        $monday = $this->now->startOfDay()->subDays($this->now->dayOfWeekIso - 1);

        [$slots, $now] = match ($kind) {
            'hours' => [array_map(static fn (int $h): string => sprintf('%02d:00', $h), range(0, 23)), ['slot' => $this->now->hour, 'fraction' => round($this->now->minute / 60, 2)]],
            'weeks' => [array_map(static fn (int $i): string => $monday->subWeeks($i)->toDateString(), range(11, 0)), ['slot' => 11, 'fraction' => round(($this->now->dayOfWeekIso - 1 + $minutes / 1440) / 7, 3)]],
            default => [$period === 'month' ? $this->dates(['from' => $this->now->startOfMonth()->toDateString(), 'to' => $this->now->endOfMonth()->toDateString()]) : $this->dates($windows['current']), null],
        };
        $now ??= ['slot' => (int) array_search($today, $slots, true), 'fraction' => round($minutes / 1440, 3)];

        $metrics = [];

        foreach (['visits' => [$this->kinds('sv'), 'site'], 'arrivals' => [Funnel::ARRIVALS, 'app']] as $key => [$kinds, $family]) {
            $metrics[$key] = $this->series($kind, $period, $slots, $kinds, $family, $windows, true);
        }

        $signUps = $this->series($kind, $period, $slots, ['sign_up'], 'accounts', $windows, false);
        $metrics['signUps'] = $kind === 'hours'
            ? ['now' => self::cells($signUps['now']), 'previous' => self::cells($signUps['previous'])]
            : ['now' => $signUps['now'], 'previous' => $signUps['previous']];

        $start = CarbonImmutable::parse($kind === 'hours' ? $this->now->subDay()->toDateString() : $slots[0], $this->zone)->utc();
        $releases = array_values(array_filter(array_column(Releases::recent(), 'firstSeenAt'), fn (string $at): bool => CarbonImmutable::parse($at)->gte($start) && CarbonImmutable::parse($at)->lte($this->now)));

        return [
            'kind' => $kind,
            'slots' => $slots,
            'now' => $now,
            'metrics' => $metrics,
            'releases' => array_values(array_reverse($releases)),
            'breaks' => array_map(static fn (array $b): array => ['date' => $b['date'], 'note' => $b['note']], $this->breaks($start->setTimezone($this->zone)->toDateString())),
        ];
    }

    /**
     * One chart line's counts per slot, now and before, and its projection.
     *
     * @param  list<string>  $slots
     * @param  list<string>  $kinds
     * @param  array<string, mixed>  $windows
     * @return array<string, mixed>
     */
    private function series(string $kind, string $period, array $slots, array $kinds, string $family, array $windows, bool $full): array
    {
        $today = $this->now->toDateString();
        $share = $this->shares[$family];
        $since = $this->since($kinds);
        $out = [];

        if ($kind === 'hours') {
            $hour = $this->now->hour;
            $current = $this->hourCounts($kinds, $today, $family, true);
            $before = $this->hourCounts($kinds, $this->now->subDay()->toDateString(), $family, false);
            $out = [
                'now' => array_map(static fn (int $h): ?float => $h <= $hour ? $current['counts'][$h] : null, range(0, 23)),
                'previous' => $before['counts'],
                'previousEstimated' => array_fill(0, 24, $before['estimated']),
                'previousTotal' => $this->total($kinds, $this->now->subDay()->toDateString()),
            ];

            if (! $full) {
                return $out;
            }

            $usable = array_values(array_filter($windows['usual'], static fn (array $w): bool => $since !== null && $w['from'] >= $since));
            $runs = array_map(fn (array $w): array => self::running($this->hourCounts($kinds, (string) $w['partial'], $family, false)['counts']), $usable);
            $column = static fn (int $h): array => array_map(static fn (array $r): float => $r[$h], $runs);
            $out['usual'] = count($runs) >= 2 ? [
                'low' => array_map(static fn (int $h): float => min([INF, ...$column($h)]), range(0, 23)),
                'high' => array_map(static fn (int $h): float => max([0.0, ...$column($h)]), range(0, 23)),
                'mean' => array_map(static fn (int $h): float => round(array_sum($column($h)) / count($runs), 1), range(0, 23)),
                'available' => count($runs),
            ] : null;

            $n = $this->total($kinds, $today);
            $projection = FairCompare::projectDay($n, $share, $since !== null && $since <= $this->now->subDays(7)->toDateString());
            $weights = $this->weights()[$family];
            $path = null;

            if ($projection['shown']) {
                $at = static fn (int $h, int $value) => (float) round($value * FairCompare::shape($weights, 4, ($h + 1) * 60), 1);
                $path = [
                    'mean' => array_map(static fn (int $h): float => $at($h, (int) $projection['value']), range($hour, 23)),
                    'low' => array_map(static fn (int $h): float => $at($h, (int) $projection['low']), range($hour, 23)),
                    'high' => array_map(static fn (int $h): float => $at($h, (int) $projection['high']), range($hour, 23)),
                ];
            }

            $out['projection'] = [
                ...self::projection($projection, 'dayShape', $share, $projection['hidden'] === 'early' ? FairCompare::reaches($weights, 4, 0.25) : null),
                'slot' => $projection['shown'] ? ['index' => $hour, 'value' => $projection['value'], 'low' => $projection['low'], 'high' => $projection['high']] : null,
                'path' => $path, 'rate' => null, 'periodTotal' => null,
            ];

            return $out;
        }

        if ($kind === 'weeks') {
            $week = static fn (string $monday): array => array_map(static fn (int $d): string => CarbonImmutable::parse($monday)->addDays($d)->toDateString(), range(0, 6));
            $sum = fn (string $monday): int => array_sum(array_map(fn (string $d): int => $d <= $today ? $this->total($kinds, $d) : 0, $week($monday)));
            $nowLine = array_map(static fn (string $m): float => (float) $sum($m), $slots);
            $previous = array_map(static fn (string $m): float => (float) $sum(CarbonImmutable::parse($m)->subWeeks(12)->toDateString()), $slots);
            $out = ['now' => $nowLine, 'previous' => $previous, 'previousEstimated' => array_fill(0, count($slots), false), 'previousTotal' => (int) array_sum($previous)];

            if (! $full) {
                return $out;
            }

            // A usual week's shape from the 4 whole weeks before this one.
            $byWeekday = array_fill(0, 7, 0);

            for ($i = 1; $i <= 4; $i++) {
                foreach ($week(CarbonImmutable::parse($slots[11])->subWeeks($i)->toDateString()) as $d => $date) {
                    $byWeekday[$d] += $this->total($kinds, $date);
                }
            }

            $shares = FairCompare::weekShares($byWeekday);
            $weekday = $this->now->dayOfWeekIso - 1;
            $w = FairCompare::weekDone($shares, $weekday, $share);
            $history = $since !== null && $since <= CarbonImmutable::parse($slots[11])->subWeeks(2)->toDateString();
            $projection = FairCompare::projectWeek((int) $nowLine[11], $w, $history);
            $out['projection'] = [
                ...self::projection($projection, 'weekShape', round($w, 3), $projection['hidden'] === 'early' ? $this->weekReaches($shares, $family) : null),
                'slot' => $projection['shown'] ? ['index' => 11, 'value' => $projection['value'], 'low' => $projection['low'], 'high' => $projection['high']] : null,
                'path' => null, 'rate' => null,
                'periodTotal' => $projection['shown'] ? (int) round(array_sum(array_slice($nowLine, 0, 11)) + (int) $projection['value']) : null,
            ];

            return $out;
        }

        // Days: 7 days and 4 weeks against the same position before; the month against last month's same day.
        $back = $period === '28d' ? 28 : 7;
        $lastMonth = $this->now->startOfMonth()->subMonthNoOverflow();
        $previousOf = fn (string $date): ?string => match ($period) {
            'month' => CarbonImmutable::parse($date)->day <= $lastMonth->daysInMonth ? $lastMonth->addDays(CarbonImmutable::parse($date)->day - 1)->toDateString() : null,
            default => CarbonImmutable::parse($date)->subDays($back)->toDateString(),
        };
        $nowLine = array_map(fn (string $d): ?float => $d <= $today ? (float) $this->total($kinds, $d) : null, $slots);
        $previous = array_map(fn (string $d): ?float => ($p = $previousOf($d)) !== null ? (float) $this->total($kinds, $p) : null, $slots);
        $previousTotal = $period === 'month'
            ? array_sum(array_map(fn (string $d): int => $this->total($kinds, $d), $this->dates(['from' => $lastMonth->toDateString(), 'to' => $lastMonth->endOfMonth()->toDateString()])))
            : (int) array_sum(array_filter($previous, static fn (?float $v): bool => $v !== null));
        $out = ['now' => $nowLine, 'previous' => $previous, 'previousEstimated' => array_fill(0, count($slots), false), 'previousTotal' => (int) $previousTotal];

        if (! $full) {
            return $out;
        }

        $index = (int) array_search($today, $slots, true);
        $history = $since !== null && $since <= $this->now->subDays(7)->toDateString();
        $day = FairCompare::projectDay($this->total($kinds, $today), $share, $history);
        $slot = $day['shown'] ? ['index' => $index, 'value' => $day['value'], 'low' => $day['low'], 'high' => $day['high']] : null;
        $before = (int) array_sum(array_slice(array_map(static fn (?float $v): float => (float) $v, $nowLine), 0, $index));

        if ($period === 'month') {
            $elapsed = ($this->now->day - 1) + $share;
            $month = FairCompare::projectMonth($before + $this->total($kinds, $today), $elapsed, $this->now->daysInMonth, $history);
            $out['projection'] = [
                ...self::projection($month, 'dailyRate', round($elapsed / $this->now->daysInMonth, 3), $month['hidden'] === 'early' ? $this->now->startOfMonth()->addDays(3)->toDateString() : null),
                'slot' => $slot, 'path' => null, 'rate' => $month['rate'], 'periodTotal' => $month['value'],
            ];

            return $out;
        }

        $out['projection'] = [
            ...self::projection($day, 'dayShape', $share, $day['hidden'] === 'early' ? FairCompare::reaches($this->weights()[$family], 4, 0.25) : null),
            'slot' => $slot, 'path' => null, 'rate' => null,
            'periodTotal' => $slot !== null ? $before + (int) $slot['value'] : null,
        ];

        return $out;
    }

    /**
     * @param  array{shown: bool, hidden: string|null, value: int|null, low: int|null, high: int|null}  $projection
     * @return array<string, mixed>
     */
    private static function projection(array $projection, string $basis, float $share, ?string $showsFrom): array
    {
        return [
            'shown' => $projection['shown'], 'hidden' => $projection['hidden'], 'showsFrom' => $showsFrom,
            'basis' => $basis, 'share' => round($share, 3),
            'value' => $projection['value'], 'low' => $projection['low'], 'high' => $projection['high'],
        ];
    }

    /**
     * When a usual week is a quarter in, as a weekday and part of the day ("tuesday-afternoon").
     *
     * @param  list<float>  $shares
     */
    private function weekReaches(array $shares, string $family): ?string
    {
        $weights = $this->weights()[$family];
        $names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach (range(0, 6) as $d) {
            foreach (range(1, 24) as $h) {
                if (FairCompare::weekDone($shares, $d, FairCompare::shape($weights, 4, $h * 60)) >= 0.25) {
                    return $names[$d].'-'.($h <= 12 ? 'morning' : ($h <= 18 ? 'afternoon' : 'evening'));
                }
            }
        }

        return null;
    }

    /**
     * A day's count in each local hour (24): exact from its hours (shared out to its total), else its total
     * spread by a usual day's shape (today: only up to now). The autumn's repeated hour is one slot.
     *
     * @param  list<string>  $kinds
     * @return array{counts: list<float>, estimated: bool}
     */
    private function hourCounts(array $kinds, string $day, string $family, bool $isToday): array
    {
        $total = $this->total($kinds, $day);
        $start = CarbonImmutable::parse($day, $this->zone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($day, $this->zone)->addDay()->startOfDay()->utc();
        $slots = array_fill(0, 24, 0);

        foreach ($kinds as $kind) {
            foreach ($this->hours[$kind] ?? [] as $hour => $n) {
                $at = CarbonImmutable::parse($hour, 'UTC');

                if ($at->gte($start) && $at->lt($end)) {
                    $slots[(int) $at->setTimezone($this->zone)->format('G')] += $n;
                }
            }
        }

        $counted = array_sum($slots);
        $covered = array_diff($kinds, self::BACKFILLED) === [] || ($this->hourlySince !== null && $start->gte($this->hourlySince));

        if ($total === 0 || ($covered && $counted > 0)) {
            return ['counts' => array_map(static fn (int $n): float => $total === 0 ? 0.0 : round($isToday ? (float) $n : $total * $n / $counted, 1), $slots), 'estimated' => false];
        }

        $weights = $this->weights()[$family];
        $byNow = max(1e-9, FairCompare::shape($weights, 4, $this->now->hour * 60 + $this->now->minute));
        $counts = [];

        for ($h = 0; $h < 24; $h++) {
            $piece = FairCompare::shape($weights, 4, ($h + 1) * 60) - FairCompare::shape($weights, 4, $h * 60);
            $counts[] = round($isToday ? ($h <= $this->now->hour ? $total * $piece / $byNow : 0.0) : $total * $piece, 1);
        }

        return ['counts' => $counts, 'estimated' => true];
    }

    /**
     * @param  list<float>  $counts
     * @return list<float>
     */
    private static function running(array $counts): array
    {
        $total = 0.0;

        return array_map(static function (float $n) use (&$total): float {
            $total += $n;

            return round($total, 1);
        }, $counts);
    }

    /**
     * Six 4-hour cells from 24 hourly counts (sign-ups' strip on Today): null once every hour in a cell is.
     *
     * @param  list<float|null>  $hours
     * @return list<float|null>
     */
    private static function cells(array $hours): array
    {
        return array_map(static function (int $c) use ($hours): ?float {
            $part = array_slice($hours, $c * 4, 4);

            return array_filter($part, static fn (?float $v): bool => $v !== null) === [] ? null : (float) array_sum($part);
        }, range(0, 5));
    }

    /**
     * A window's days, in order.
     *
     * @param  array<string, mixed>  $window
     * @return list<string>
     */
    private function dates(array $window): array
    {
        $dates = [];

        for ($d = CarbonImmutable::parse((string) $window['from']); $d->toDateString() <= (string) $window['to']; $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }

    /**
     * The old tiles, cut at the same time: today so far, yesterday and the same weekday last week by now,
     * and the period, the one before and the same a week back.
     *
     * @param  array<string, mixed>  $windows
     * @param  array<string, mixed>  $shifted
     * @return list<array<string, mixed>>
     */
    private function tiles(array $windows, array $shifted): array
    {
        $tiles = [];
        $today = FairCompare::windows('today', $this->now, $this->zone);
        $lastWeek = FairCompare::windows('today', $this->now->subDays(7), $this->zone);

        foreach (self::TILES as $key => $kinds) {
            $family = in_array($key, ['signedUp', 'plusStarted'], true) ? 'accounts' : 'app';
            $current = (int) round($this->value($kinds, $windows['current'], $family, true)['value']);
            $tiles[] = [
                'key' => $key,
                'today' => $this->total($kinds, $this->now->toDateString()),
                'yesterday' => (int) round($this->value($kinds, $today['previous'], $family, false)['value']),
                'lastWeek' => (int) round($this->value($kinds, $lastWeek['current'], $family, false)['value']),
                'current' => $current,
                'previous' => (int) round($this->value($kinds, $windows['previous'], $family, false)['value']),
                'weekAgo' => (int) round($this->value($kinds, $shifted['current'], $family, false)['value']),
                'lowSample' => $current < Growth::LOW_SAMPLE,
            ];
        }

        return $tiles;
    }

    /**
     * @return list<array{key: string, total: int}>
     */
    private function steps(string $from): array
    {
        $steps = [];

        foreach (self::STEPS as $key => $kinds) {
            $total = 0;

            foreach ($this->days as $date => $byKind) {
                if ($date >= $from) {
                    $total += array_sum(array_map(static fn (string $kind): int => $byKind[$kind] ?? 0, $kinds));
                }
            }

            $steps[] = ['key' => $key, 'total' => $total];
        }

        return $steps;
    }

    /**
     * The counting changes a comparison crosses (config counts.breaks): a baseline before one reads differently.
     *
     * @return list<array{date: string, kinds: list<string>, note: string}>
     */
    private function breaks(string $earliest): array
    {
        $breaks = [];

        foreach ((array) config('counts.breaks') as $date => $entry) {
            if (! is_string($date) || $date <= $earliest || $date > $this->now->toDateString()) {
                continue;
            }
            // One break on a day, or a list of them when two changes went out the same day.
            foreach (array_is_list((array) $entry) ? (array) $entry : [$entry] as $break) {
                $kinds = ((array) $break)['kinds'] ?? '*';
                $breaks[] = ['date' => $date, 'kinds' => is_array($kinds) ? array_values($kinds) : ['*'], 'note' => (string) (((array) $break)['note'] ?? '')];
            }
        }

        return $breaks;
    }
}
