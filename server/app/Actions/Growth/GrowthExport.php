<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Actions\Counts\Funnel;
use App\Console\Commands\RecordPageVersions;
use App\Models\DropoffReport;
use App\Models\OutreachMessage;
use App\Models\OutreachTarget;
use App\Support\Admin\Insights;
use App\Support\Admin\Revenue;
use App\Support\Ops\Releases;
use App\Support\Outreach\Outreach;
use App\Support\Site\SitePages;
use App\Support\Site\SiteVisit;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The growth data (docs/growth-data.md): one JSON document of numbers for a dev machine to load
 * (app:pull-growth), built from the same anonymous daily counts and our own tables as Admin's
 * Usage, Funnel and Growth pages. Aggregates only: no person's row, no id of any kind (not even a
 * hashed one), no name, email, handle, IP, song title, free text, organization or token. Any group
 * small enough to stand for a person is folded or suppressed. Days are the report timezone's.
 */
final class GrowthExport
{
    /** Bump when a field's shape or meaning changes, and say so in docs/growth-data.md. */
    public const SCHEMA_VERSION = 17;

    /** The range-scoped sections' windows, in days (null: since the first count). */
    public const RANGES = ['7' => 7, '30' => 30, '90' => 90, 'all' => null];

    /** Every top-level key, in order (GrowthDataDictionaryTest checks each is documented). */
    public const SECTIONS = ['meta', 'daily', 'funnel', 'signups', 'cohorts', 'courses', 'sources', 'siteSections', 'partPlays', 'plus', 'outreach', 'releases', 'dropoffs', 'sharing', 'breadth'];

    /** The sections `meta.range` applies to; the rest say their own windows. */
    public const RANGED = ['funnel', 'courses', 'sources', 'siteSections', 'partPlays'];

    /** Days `daily` and `signups.days` cover. */
    public const DAILY_DAYS = 180;

    /** Weeks `signups.weeks` and `cohorts.weeks` cover. */
    public const WEEKS = 26;

    /** A country (accounts or counts) with fewer than this is folded into (other). */
    public const COUNTRY_MIN = 5;

    /** Any other group with fewer than this is folded or suppressed. */
    public const GROUP_MIN = 3;

    /** The days after signing up a cohort's return windows cover, [from, to) in whole days. */
    public const RETURNS = ['day1' => [1, 2], 'days7to13' => [7, 14], 'days30to59' => [30, 60]];

    /** Each source's columns, from the counts' kinds (Growth's, so the two read alike). */
    private const SOURCE_COLUMNS = ['visits' => ['site_visit'], 'arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'courseLesson' => ['f_course_lesson'], 'signedUp' => ['f_account'], 'day2' => ['f_day2'], 'week1' => ['f_week1']];

    /** The courses' detailed kinds (config counts.detailed): started and finished. */
    private const COURSE_KINDS = ['pl_s' => 'started', 'pl_d' => 'finished', 'fl_s' => 'started', 'fl_d' => 'finished'];

    public function __construct(private readonly Funnel $funnel, private readonly Revenue $revenue, private readonly Insights $insights) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $range): array
    {
        $range = array_key_exists($range, self::RANGES) ? $range : '30';
        $today = ReportDay::now()->startOfDay();
        $days = self::RANGES[$range] ?? max(1, (int) $today->diffInDays(Carbon::parse($this->firstCount() ?? $today->toDateString(), ReportDay::zone())) + 1);
        $from = $today->copy()->subDays($days - 1)->toDateString();

        return [
            'meta' => [
                'schema_version' => self::SCHEMA_VERSION,
                'generated_at' => now()->utc()->toIso8601ZuluString(),
                'timezone' => ReportDay::zone(),
                'range' => ['name' => $range, 'days' => $days, 'from' => $from, 'to' => $today->toDateString()],
                'range_applies_to' => self::RANGED,
                'app_commit' => config('app.build') ?: null,
                'built_at' => config('app.built_at'),
                // The sitemap's last dating run (app:sitemap:dates, after each deploy and daily): our own page paths only.
                'sitemapDates' => Cache::get(RecordPageVersions::LAST),
                'notes' => self::notes(),
            ],
            'daily' => $this->daily($today),
            'funnel' => $this->funnel($days),
            'signups' => $this->signups($today),
            'cohorts' => $this->cohorts($today),
            'courses' => $this->courses($from),
            'sources' => $this->sources($from),
            'siteSections' => $this->siteSections($from),
            'partPlays' => $this->partPlays($from),
            'plus' => $this->plus(),
            'outreach' => $this->outreach($today),
            'releases' => Releases::recent(),
            // The latest weekly drop-off report (App\Actions\Growth\Dropoffs), or null before the first.
            'dropoffs' => DropoffReport::query()->orderByDesc('week_end')->first()?->data,
            // This week's sharing beside last week's (App\Actions\Growth\Sharing): totals by surface only.
            'sharing' => app(Sharing::class)->build(),
            // How much of the app singers use, this week and the four before (App\Actions\Growth\Breadth): br and ft totals only.
            'breadth' => app(Breadth::class)->build(),
        ];
    }

    /**
     * Caveats that travel with the numbers.
     *
     * @return list<string>
     */
    private static function notes(): array
    {
        return [
            'Every count is anonymous and first-party: a device counts itself, so a blocked or offline device is missing, and one person on two devices is two.',
            'Days are the report timezone\'s (meta.timezone); the newest day and week are partial.',
            'Admins\' own accounts and devices are left out everywhere.',
            'Accounts deleted since are not in signups or cohorts: they are gone from the database.',
            'Cohort returns use only what is already kept: a take recorded or a practice score saved. Opening the app, a lesson without a score, or listening is not seen, so returns read low. Day-N return of any other kind would need per-person tracking and is left out.',
            'A cohort or group under '.self::GROUP_MIN.' accounts is suppressed (its measures null), and a country under '.self::COUNTRY_MIN.' is folded into (other).',
            'Every course became free with an account on 2026-11-06, and a guest is asked for an account at the end of a course\'s first lesson (ask_acct_course_*). Compare courses on either side of that day, not across it.',
            'Since the deploy after 2026-11-07 (see releases), the lesson-2 account wall also counts as the course ask, acct_course (shown once a device, yes, and Back to the course as not now), so ask_acct_course_* reads as the whole course ask from then; before, only the end-of-lesson-1 ask.',
            'Outreach sends stopped carrying an organization\'s ?ref= tag on 2026-11-05, so outreach.results counts only the earlier sends.',
            'releases starts on the day it shipped: earlier deploys were not recorded.',
            'site_visit (sources[].visits) is the landing\'s page loads alone, never the rest of the site. Every public page\'s loads by section are siteSections (sv_*), counted from the deploy after 2026-11-08.',
            'siteSections\' songs counted a song\'s part pages too (sv_songs) from that deploy, about 18:17 on 2026-11-08, until the next one (see releases), when part pages became their own section, part (sv_part), and partPlays began. Read songs and part across that day together.',
            'From the deploy after 2026-11-08, a well-known crawler\'s counts (search, SEO and AI bots, link previews; by user agent, never kept) are dropped, every kind alike, so every count reads a little lower from then.',
        ];
    }

    private function firstCount(): ?string
    {
        $day = DB::table('usage_counts')->min('day');

        return $day !== null ? substr((string) $day, 0, 10) : null;
    }

    /**
     * Every count kind by day, guests and accounts apart (Admin's Usage), as columns over the dates,
     * and the countries the counts came from.
     *
     * @return array<string, mixed>
     */
    private function daily(Carbon $today): array
    {
        $from = $today->copy()->subDays(self::DAILY_DAYS - 1);
        $dates = [];

        for ($day = $from->copy(); $day->lte($today); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        $index = array_flip($dates);
        $zeros = array_fill(0, count($dates), 0);
        $kinds = [];
        $rows = DB::table('usage_counts')->where('day', '>=', $from->toDateString())
            ->selectRaw("day, kind, sum(case when audience = 'guest' then count else 0 end) as guest, sum(case when audience = 'account' then count else 0 end) as account")
            ->groupBy('day', 'kind')->get();

        foreach ($rows as $row) {
            $at = $index[substr((string) $row->day, 0, 10)] ?? null;

            if ($at === null) {
                continue;
            }

            $kinds[(string) $row->kind] ??= ['guest' => $zeros, 'account' => $zeros];
            $kinds[(string) $row->kind]['guest'][$at] = (int) $row->guest;
            $kinds[(string) $row->kind]['account'][$at] = (int) $row->account;
        }

        ksort($kinds);
        $countries = DB::table('usage_counts')->where('day', '>=', $from->toDateString())->selectRaw('country, sum(count) as total')->groupBy('country')->pluck('total', 'country')
            ->mapWithKeys(static fn (mixed $n, mixed $country): array => [(string) $country => (int) $n])->all();

        return ['from' => $from->toDateString(), 'to' => $today->toDateString(), 'dates' => $dates, 'kinds' => $kinds, 'countries' => self::foldCountries($countries)];
    }

    /**
     * The onboarding funnel over the range (Admin's Funnel, every device as one arm), and each
     * experiment's arms over its whole run. By day, the steps are in `daily` (the f_* kinds).
     *
     * @return array<string, mixed>
     */
    private function funnel(int $days): array
    {
        $all = $this->funnel->report(null, $days);
        $experiments = [];

        foreach ($this->funnel->experiments() as $experiment) {
            $report = $this->funnel->report($experiment['id'], $days);
            $experiments[] = [...$experiment, 'from' => $report['from'], 'to' => $report['to'], 'split' => $report['split'], 'enough' => $report['enough'], 'steps' => $report['steps'], 'asks' => $report['asks']];
        }

        return ['from' => $all['from'], 'to' => $all['to'], 'steps' => $all['steps'], 'asks' => $all['asks'], 'experiments' => $experiments];
    }

    /**
     * Accounts made, by day and by ISO week, and over all time by country (as last signed in from)
     * and by what they said brought them (a fixed list).
     *
     * @return array<string, mixed>
     */
    private function signups(Carbon $today): array
    {
        $zone = ReportDay::zone();
        $firstDay = $today->copy()->subDays(self::DAILY_DAYS - 1);
        $firstWeek = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);
        $since = $firstDay->lt($firstWeek) ? $firstDay : $firstWeek;
        $days = [];
        $weeks = [];

        for ($day = $firstDay->copy(); $day->lte($today); $day->addDay()) {
            $days[$day->toDateString()] = 0;
        }

        for ($week = $firstWeek->copy(); $week->lte($today); $week->addWeek()) {
            $weeks[$week->toDateString()] = 0;
        }

        $people = DB::table('users')->where('is_admin', false);

        foreach ((clone $people)->where('created_at', '>=', $since->copy()->utc())->pluck('created_at') as $at) {
            $local = Carbon::parse((string) $at, 'UTC')->setTimezone($zone);
            $day = $local->toDateString();
            $week = $local->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

            if (isset($days[$day])) {
                $days[$day]++;
            }

            if (isset($weeks[$week])) {
                $weeks[$week]++;
            }
        }

        $countries = (clone $people)->selectRaw('coalesce(country, \'\') as country, count(*) as n')->groupBy(DB::raw('coalesce(country, \'\')'))->pluck('n', 'country')
            ->mapWithKeys(static fn (mixed $n, mixed $c): array => [(string) $c => (int) $n])->all();
        $heard = (clone $people)->selectRaw('coalesce(heard_from, \'\') as heard, count(*) as n')->groupBy(DB::raw('coalesce(heard_from, \'\')'))->pluck('n', 'heard')
            ->mapWithKeys(static fn (mixed $n, mixed $h): array => [(string) $h === '' ? '(none)' : (string) $h => (int) $n])->all();

        return [
            'total' => (clone $people)->count(),
            'days' => ['dates' => array_keys($days), 'accounts' => array_values($days)],
            'weeks' => ['weeks' => array_keys($weeks), 'accounts' => array_values($weeks)],
            'countries' => self::foldCountries($countries),
            'heardFrom' => self::fold($heard, self::COUNTRY_MIN),
        ];
    }

    /**
     * By sign-up week: how many came back to sing (a take or a practice score) in each return
     * window, null until every account in the week has had the whole window, or when the week has
     * too few accounts. And Admin's cohorts by sign-up month, suppressed the same way.
     *
     * @return array<string, mixed>
     */
    private function cohorts(Carbon $today): array
    {
        $zone = ReportDay::zone();
        $firstWeek = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);
        $start = $firstWeek->copy()->utc();
        $users = DB::table('users')->where('is_admin', false)->where('created_at', '>=', $start)->pluck('created_at', 'id');

        // The whole days after signing up that each account sang on (0 is the sign-up day itself).
        $sang = [];
        $last = max(array_map(static fn (array $w): int => $w[1], self::RETURNS));
        $rows = [...self::singing('takes', 'coalesce(takes.recorded_by, takes.user_id)', 'takes.created_at', $start, $last), ...self::singing('scores', 'scores.user_id', 'scores.recorded_at', $start, $last)];

        foreach ($rows as $row) {
            $sang[(int) $row->person][(int) $row->d] = true;
        }

        $weeks = [];

        for ($week = $firstWeek->copy(); $week->lte($today); $week->addWeek()) {
            $weeks[$week->toDateString()] = ['ids' => []];
        }

        foreach ($users as $id => $at) {
            $week = Carbon::parse((string) $at, 'UTC')->setTimezone($zone)->startOfWeek(Carbon::MONDAY)->toDateString();

            if (isset($weeks[$week])) {
                $weeks[$week]['ids'][] = (int) $id;
            }
        }

        $rows = [];

        foreach ($weeks as $week => $group) {
            $size = count($group['ids']);
            $suppressed = $size > 0 && $size < self::GROUP_MIN;
            // Everyone in the week has had the window once its last sign-up could have.
            $lastSignUp = Carbon::parse($week, $zone)->addWeek();
            $row = ['week' => $week, 'signedUp' => $size, 'suppressed' => $suppressed];

            foreach (self::RETURNS as $key => [$from, $to]) {
                $matured = $lastSignUp->copy()->addDays($to)->lte(now());
                $back = count(array_filter($group['ids'], static function (int $id) use ($sang, $from, $to): bool {
                    for ($d = $from; $d < $to; $d++) {
                        if (isset($sang[$id][$d])) {
                            return true;
                        }
                    }

                    return false;
                }));
                $row[$key] = $matured && ! $suppressed && $size > 0 ? $back : null;
            }

            $rows[] = $row;
        }

        $months = array_map(static function (array $month): array {
            if ($month['signedUp'] > 0 && $month['signedUp'] < self::GROUP_MIN) {
                return ['month' => $month['month'], 'signedUp' => $month['signedUp'], 'suppressed' => true, 'finishedSong' => null, 'secondSong' => null, 'active' => null, 'plus' => null, 'medianDaysToFirstSong' => null];
            }

            return ['month' => $month['month'], 'signedUp' => $month['signedUp'], 'suppressed' => false, ...array_diff_key($month, ['month' => true, 'signedUp' => true])];
        }, $this->insights->cohorts());

        return ['returns' => self::RETURNS, 'weeks' => $rows, 'months' => $months];
    }

    /**
     * Each account signed up since `$start` and the whole days after signing up it sang on, from
     * one table: who sang (`$who`) and when (`$at`), both SQL of our own, never a value.
     *
     * @param  literal-string  $table
     * @param  literal-string  $who
     * @param  literal-string  $at
     * @return list<object{person: int|string, d: int|string}>
     */
    private static function singing(string $table, string $who, string $at, Carbon $start, int $before): array
    {
        /** @var list<object{person: int|string, d: int|string}> */
        return DB::table($table)->join('users', 'users.id', '=', DB::raw($who))
            ->where('users.is_admin', false)->where('users.created_at', '>=', $start)->where($at, '>=', $start)
            ->whereRaw($at.' >= users.created_at')
            ->selectRaw('users.id as person, floor(timestampdiff(second, users.created_at, '.$at.') / 86400) as d')
            ->groupBy('person', 'd')->havingRaw('d < ?', [$before])->get()->all();
    }

    /**
     * Lessons started and finished over the range: in all, per course (the song and Plus courses
     * count by course) and per lesson of the free course, guests and accounts apart; and the
     * account ask at the end of a guest's first lesson of a course.
     *
     * @return array<string, mixed>
     */
    private function courses(string $from): array
    {
        $split = "sum(case when audience = 'guest' then count else 0 end) as guest, sum(case when audience = 'account' then count else 0 end) as account";
        $asks = self::accountAsks();
        $askKinds = array_merge(...array_map(static fn (string $ask): array => ['ask_'.$ask.'_shown', 'ask_'.$ask.'_yes', 'ask_'.$ask.'_not'], $asks));
        $rows = DB::table('usage_counts')->where('day', '>=', $from)
            ->where(fn ($q) => $q->whereIn('kind', ['lesson_start', 'lesson_done', 'f_lesson1', ...$askKinds])
                ->orWhere('kind', 'like', 'pl\_%')->orWhere('kind', 'like', 'fl\_%')->orWhere('kind', 'like', 'fs\_r:%'))
            ->selectRaw("kind, {$split}")->groupBy('kind')->get()
            ->mapWithKeys(static fn (object $r): array => [(string) $r->kind => ['guest' => (int) $r->guest, 'account' => (int) $r->account]])->all();

        $zero = ['guest' => 0, 'account' => 0];
        $courses = [];
        $lessons = [];

        foreach ($rows as $kind => $n) {
            [$prefix, $id] = array_pad(explode(':', $kind, 2), 2, null);

            // A free lesson's step reached: its lesson's `steps`, by step.
            if ($prefix === 'fs_r' && $id !== null && preg_match('/^(.+)\.(\d+)$/', $id, $step) === 1) {
                $lessons[$step[1]] ??= ['lesson' => $step[1], 'started' => $zero, 'finished' => $zero, 'steps' => []];
                $lessons[$step[1]]['steps'][(int) $step[2]] = $n;

                continue;
            }

            if ($id === null || ! isset(self::COURSE_KINDS[$prefix])) {
                continue;
            }

            $what = self::COURSE_KINDS[$prefix];

            if (str_starts_with($prefix, 'pl')) {
                $courses[$id] ??= ['course' => $id, 'started' => $zero, 'finished' => $zero];
                $courses[$id][$what] = $n;
            } else {
                $lessons[$id] ??= ['lesson' => $id, 'started' => $zero, 'finished' => $zero, 'steps' => []];
                $lessons[$id][$what] = $n;
            }
        }

        ksort($courses);
        ksort($lessons, SORT_NATURAL);

        // Each lesson's steps in order from 0, a step with none reached as zero.
        foreach ($lessons as $id => $lesson) {
            $reached = $lesson['steps'];
            $lessons[$id]['steps'] = $reached === [] ? [] : array_map(static fn (int $i): mixed => $reached[$i] ?? $zero, range(0, max(array_keys($reached))));
        }

        return [
            'lessons' => ['started' => $rows['lesson_start'] ?? $zero, 'finished' => $rows['lesson_done'] ?? $zero, 'firstLesson' => $rows['f_lesson1'] ?? $zero],
            'byCourse' => array_values($courses),
            'freeCourse' => array_values($lessons),
            'accountAsk' => ['shown' => $rows['ask_acct_course_shown'] ?? $zero, 'yes' => $rows['ask_acct_course_yes'] ?? $zero, 'notNow' => $rows['ask_acct_course_not'] ?? $zero],
            // Every ask for an account, by what asked; notNow is null for an ask without a Not now count.
            'accountAsks' => array_map(static fn (string $ask): array => [
                'ask' => $ask,
                'shown' => $rows['ask_'.$ask.'_shown'] ?? $zero,
                'yes' => $rows['ask_'.$ask.'_yes'] ?? $zero,
                'notNow' => in_array('ask_'.$ask.'_not', (array) config('counts.kinds'), true) ? ($rows['ask_'.$ask.'_not'] ?? $zero) : null,
            ], $asks),
        ];
    }

    /**
     * The asks for an account (config counts.asks): the first-run ask, `account`, and each nudge, `acct_*`.
     *
     * @return list<string>
     */
    private static function accountAsks(): array
    {
        return array_values(array_filter((array) config('counts.asks'), static fn (mixed $ask): bool => is_string($ask) && ($ask === 'account' || str_starts_with($ask, 'acct_'))));
    }

    /**
     * Each live tool page's own tag (a tool, its variants, the hub and the embed page): ours, not a visitor's, so
     * listed even at nothing and never folded.
     *
     * @return list<string>
     */
    private static function toolTags(): array
    {
        return [...array_map(static fn (string $slug): string => 'tool-'.$slug, [...SitePages::listed(), ...SitePages::variants()]), 'tools', 'tools-embed'];
    }

    /**
     * The tags a singer's shared card or link carries (share-range, share-lesson): every one has its row.
     *
     * @return list<string>
     */
    private static function shareTags(): array
    {
        return array_values(array_filter((array) config('counts.sources'), static fn (mixed $tag): bool => is_string($tag) && str_starts_with($tag, 'share-')));
    }

    /**
     * Every public page's loads over the range by section (sv_<section>, App\Support\Site\SiteVisit), each
     * section listed in order, at nothing too: anonymous counts with no source and no path.
     *
     * @return list<array{section: string, visits: int}>
     */
    private function siteSections(string $from): array
    {
        $totals = DB::table('usage_counts')->whereIn('kind', array_map(static fn (string $section): string => 'sv_'.$section, SiteVisit::SECTIONS))->where('day', '>=', $from)->selectRaw('kind, sum(count) as total')->groupBy('kind')->pluck('total', 'kind');

        return array_map(static fn (string $section): array => ['section' => $section, 'visits' => (int) ($totals['sv_'.$section] ?? 0)], SiteVisit::SECTIONS);
    }

    /**
     * A song part page's loads and the loads that played its part (part_play, once a page load), over the range.
     *
     * @return array{visits: int, plays: int}
     */
    private function partPlays(string $from): array
    {
        $totals = DB::table('usage_counts')->whereIn('kind', ['sv_part', 'part_play'])->where('day', '>=', $from)->selectRaw('kind, sum(count) as total')->groupBy('kind')->pluck('total', 'kind');

        return ['visits' => (int) ($totals['sv_part'] ?? 0), 'plays' => (int) ($totals['part_play'] ?? 0)];
    }

    /**
     * Where people came from over the range, by the ?ref= source the counts keep (counts.sources,
     * the free tools' and embeds' tags), each typed; an organization's outreach tag reads only as
     * outreach, and a source with fewer than GROUP_MIN visits and arrivals is folded into (other),
     * except a tool's own tag and a share's: every live tool page and every share tag has its row, at nothing too.
     *
     * @return list<array<string, int|string>>
     */
    private function sources(string $from): array
    {
        $kinds = array_values(array_unique(array_merge(...array_values(self::SOURCE_COLUMNS))));
        $totals = [];

        foreach (DB::table('usage_counts')->whereIn('kind', $kinds)->where('day', '>=', $from)->selectRaw('source, kind, sum(count) as total')->groupBy('source', 'kind')->get() as $row) {
            $source = (string) $row->source;
            $source = match (true) {
                $source === '' => '(none)',
                str_starts_with($source, Outreach::REF_PREFIX) => 'outreach',
                default => $source,
            };
            $totals[$source][(string) $row->kind] = ($totals[$source][(string) $row->kind] ?? 0) + (int) $row->total;
        }

        foreach ([...self::toolTags(), ...self::shareTags()] as $tag) {
            $totals[$tag] ??= [];
        }

        $rows = [];
        $other = ['source' => '(other)', 'type' => 'folded', 'groups' => 0, ...array_fill_keys(array_keys(self::SOURCE_COLUMNS), 0)];

        foreach ($totals as $source => $byKind) {
            $sum = static fn (string $column): int => array_sum(array_map(static fn (string $kind): int => $byKind[$kind] ?? 0, self::SOURCE_COLUMNS[$column]));
            $row = ['source' => $source, 'type' => self::sourceType($source), 'visits' => $sum('visits'), 'arrivals' => $sum('arrivals'), 'lesson1' => $sum('lesson1'), 'courseLesson' => $sum('courseLesson'), 'signedUp' => $sum('signedUp'), 'day2' => $sum('day2'), 'week1' => $sum('week1')];

            if (! in_array($row['type'], ['tool', 'share'], true) && max($row['visits'], $row['arrivals']) < self::GROUP_MIN) {
                $other['groups']++;

                foreach (array_keys(self::SOURCE_COLUMNS) as $column) {
                    $other[$column] += $row[$column];
                }

                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => [$b['arrivals'], $b['visits'], $a['source']] <=> [$a['arrivals'], $a['visits'], $b['source']]);

        return $other['groups'] > 0 ? [...$rows, $other] : $rows;
    }

    private static function sourceType(string $source): string
    {
        return match (true) {
            $source === '(none)' => 'none',
            $source === 'outreach' => 'outreach',
            str_starts_with($source, 'tool-') || in_array($source, ['tools', 'tools-embed'], true) => 'tool',
            str_starts_with($source, 'embed-') => 'embed',
            str_starts_with($source, 'share-') => 'share',
            in_array($source, ['harmony', 'coach', 'profile', 'other'], true) => 'group',
            default => 'campaign',
        };
    }

    /**
     * Plus: MRR now and a month ago, subscribers by state, store and plan, and the last 12 months'
     * MRR, new and lost (Admin's Revenue), with each month's churn. Coaching's one-off payments are
     * left out (a coach's earnings would stand for a person).
     *
     * @return array<string, mixed>
     */
    private function plus(): array
    {
        $revenue = $this->revenue->show();

        return [
            'mrr' => $revenue['mrr'],
            'arr' => $revenue['arr'],
            'monthAgo' => $revenue['monthAgo'],
            'unpriced' => $revenue['unpriced'],
            'subscribers' => $revenue['subscribers'],
            'months' => $revenue['months'],
            'churn' => $this->revenue->churn(),
        ];
    }

    /**
     * Outreach by angle (OutreachTarget::ANGLES): organizations, first emails sent, replied, a yes,
     * and what the anonymous counts say the tagged sends brought (null under Outreach::FOLD_UNDER
     * arrivals); and first emails sent per ISO week. No organization is named.
     *
     * @return array<string, mixed>
     */
    private function outreach(Carbon $today): array
    {
        $targets = OutreachTarget::query()->withExists(['messages as contacted' => fn ($q) => $q->where('kind', OutreachMessage::FIRST)->whereNotNull('sent_at')])
            ->withExists('replies as replied')->get(['id', 'angle', 'status', 'outcome', 'ref']);
        $kinds = array_values(array_unique(array_merge(...array_values(Outreach::RESULTS))));
        $byRef = [];

        foreach (DB::table('usage_counts')->where('source', 'like', Outreach::REF_PREFIX.'%')->whereIn('kind', $kinds)->selectRaw('source, kind, sum(count) as total')->groupBy('source', 'kind')->get() as $row) {
            $byRef[(string) $row->source][(string) $row->kind] = (int) $row->total;
        }

        $angles = [];

        foreach ($targets->groupBy('angle') as $angle => $group) {
            $results = array_fill_keys(array_keys(Outreach::RESULTS), 0);

            foreach ($group as $target) {
                foreach (Outreach::RESULTS as $column => $of) {
                    $results[$column] += array_sum(array_map(static fn (string $kind): int => $byRef[(string) $target->ref][$kind] ?? 0, $of));
                }
            }

            $few = $results['arrivals'] < Outreach::FOLD_UNDER;
            $angles[] = [
                'angle' => (string) $angle,
                'targets' => $group->count(),
                'contacted' => $group->where('contacted', true)->count(),
                'replied' => $group->where('replied', true)->count(),
                'positive' => $group->whereIn('outcome', OutreachTarget::POSITIVE)->count(),
                'byStatus' => $group->countBy('status')->sortKeys()->all(),
                'results' => $few ? null : $results,
            ];
        }

        usort($angles, static fn (array $a, array $b): int => $a['angle'] <=> $b['angle']);
        $firstWeek = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(11);
        $weeks = [];

        for ($week = $firstWeek->copy(); $week->lte($today); $week->addWeek()) {
            $weeks[$week->toDateString()] = 0;
        }

        foreach (OutreachMessage::query()->where('kind', OutreachMessage::FIRST)->where('sent_at', '>=', $firstWeek->copy()->utc())->pluck('sent_at') as $at) {
            $week = Carbon::parse($at)->setTimezone(ReportDay::zone())->startOfWeek(Carbon::MONDAY)->toDateString();

            if (isset($weeks[$week])) {
                $weeks[$week]++;
            }
        }

        return ['angles' => $angles, 'firstEmails' => ['weeks' => array_keys($weeks), 'sent' => array_values($weeks)]];
    }

    /**
     * Countries ('' is unknown) with fewer than COUNTRY_MIN folded into (other), biggest first.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private static function foldCountries(array $counts): array
    {
        $named = [];

        foreach ($counts as $country => $n) {
            $named[$country === '' ? '(unknown)' : strtoupper($country)] = $n;
        }

        return self::fold($named, self::COUNTRY_MIN);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private static function fold(array $counts, int $min): array
    {
        $out = [];
        $other = 0;

        foreach ($counts as $key => $n) {
            if ($n < $min) {
                $other += $n;
            } else {
                $out[$key] = $n;
            }
        }

        arsort($out);

        if ($other > 0) {
            $out['(other)'] = ($out['(other)'] ?? 0) + $other;
        }

        return $out;
    }
}
