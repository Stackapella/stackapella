<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use App\Support\Outreach\Outreach;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\DB;

/**
 * Adds a request's events to today's counts (config/counts.php): only the kinds on the list, each
 * capped, and nothing kept but the numbers.
 */
final class RecordCounts
{
    /** Kinds that keep where the device came from (with the funnel's): the landing's visits. */
    public const SOURCED = ['site_visit', 'school_redeemed'];

    /**
     * A funnel or ask kind (Funnel::kinds()) also keeps the device's experiment arm, when it names
     * a configured one, and a funnel kind or a landing visit its source, grouped (self::source());
     * either one, and the row keeps no country, so those cells stay coarse.
     *
     * Each funnel step also counts once for every experiment arm the device names (`variants`), in
     * experiment_counts: a daily total per arm, apart from the everyday totals.
     *
     * @param  list<array{kind: string, n?: int}>  $events
     * @param  list<string>  $variants
     */
    public function handle(array $events, bool $signedIn, ?string $country, ?string $variant = null, ?string $ref = null, array $variants = []): void
    {
        $funnel = Funnel::kinds();
        // A set: the detailed kinds run to thousands (every course's lessons and steps).
        $kinds = array_flip([...(array) config('counts.kinds'), ...$funnel, ...self::detailedKinds(), Funnel::ENROLLED]);
        $variant = $variant !== null && Funnel::knownVariant($variant) ? $variant : '';
        $source = self::source($ref);
        $max = (int) config('counts.max_n');
        $totals = [];

        foreach (array_slice($events, 0, (int) config('counts.max_events')) as $event) {
            if (is_string($event['kind'] ?? null) && isset($kinds[$event['kind']])) {
                // A funnel kind is a device's first: once a request, whatever `n` says.
                $cap = in_array($event['kind'], [...$funnel, Funnel::ENROLLED], true) ? 1 : $max;
                $totals[$event['kind']] = min($cap, ($totals[$event['kind']] ?? 0) + max(1, min($cap, (int) ($event['n'] ?? 1))));
            }
        }

        $day = ReportDay::today();
        $audience = $signedIn ? 'account' : 'guest';
        $hourly = [];

        foreach (array_diff_key($totals, [Funnel::ENROLLED => true]) as $kind => $n) {
            $arm = in_array($kind, $funnel, true) ? $variant : '';
            $from = in_array($kind, $funnel, true) || in_array($kind, self::SOURCED, true) ? $source : '';
            DB::statement('insert into usage_counts (day, kind, audience, country, variant, source, count) values (?, ?, ?, ?, ?, ?, ?) on duplicate key update count = count + ?', [$day, $kind, $audience, $arm !== '' || $from !== '' ? '' : ($country ?? ''), $arm, $from, $n, $n]);
            $hourly[] = [$kind, $from, $n];
        }

        self::hourly($audience, $hourly);

        // The experiments' own counts: funnel steps and whatever a running experiment measures besides (Funnel::measured),
        // for arms the registry knows (onb1 keeps `variant`).
        $arms = array_slice(array_values(array_unique(array_filter($variants, static fn (string $v): bool => ! str_starts_with($v, Funnel::LEGACY.'-') && Funnel::knownVariant($v)))), 0, 4);

        foreach ($arms === [] ? [] : array_intersect_key($totals, array_flip([...$funnel, Funnel::ENROLLED, ...Funnel::measured()])) as $kind => $n) {
            foreach ($arms as $arm) {
                DB::statement('insert into experiment_counts (day, variant, kind, audience, count) values (?, ?, ?, ?, ?) on duplicate key update count = count + ?', [$day, $arm, $kind, $audience, $n, $n]);
            }
        }
    }

    /** A free tool's ?ref= tag: "tool-" and the tool's name (config tools.slugs). */
    public const TOOL_PREFIX = 'tool-';

    /** usage_counts.source's length. */
    public const SOURCE_MAX = 60;

    /** An embedded tool's ?ref= tag's prefix; the embedding site's host follows. */
    public const EMBED_PREFIX = 'embed-';

    /** An embedded tool's tag: "embed-" and the embedding site's host (two labels or more). */
    public const EMBED = '/^embed-(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/';

    /**
     * Where a device came from, from its ?ref= tag, grouped: a campaign's tag as written (config
     * counts.sources), a free tool's (tool-<slug>, config tools.slugs), "harmony" for a shared harmony's
     * (h-*), "coach" for a coach's link (c-*), "profile" for a singer's profile (p-*), "other" for any
     * other, and '' for none. Never a tag that names a person or a thing of theirs, so no id is kept.
     */
    public static function source(?string $ref): string
    {
        $ref = strtolower(trim((string) $ref));

        // Longer than the column (usage_counts.source) is never one of ours.
        if (strlen($ref) > self::SOURCE_MAX) {
            return 'other';
        }

        return match (true) {
            $ref === '' => '',
            in_array($ref, (array) config('counts.sources'), true) => $ref,
            // A free tool's share card: share-<slug> (docs/retention-plan.md, section 8; the surfaces' are in sources).
            str_starts_with($ref, 'share-') && in_array(substr($ref, 6), (array) config('tools.slugs'), true) => $ref,
            str_starts_with($ref, self::TOOL_PREFIX) && in_array(substr($ref, strlen(self::TOOL_PREFIX)), (array) config('tools.slugs'), true) => $ref,
            // A free tool embedded on another site (docs/tools-plan.md): that site's host, which
            // names a website, never a person; a source of under five a day is folded in Growth.
            preg_match(self::EMBED, $ref) === 1 => $ref,
            str_starts_with($ref, 'h-') => 'harmony',
            str_starts_with($ref, 'c-') => 'coach',
            // A singer's public profile (p-<handle>): grouped, never the handle.
            str_starts_with($ref, 'p-') => 'profile',
            // An organization's own outreach link (docs/outreach-plan.md): its tag, one per organization.
            Outreach::known($ref) => $ref,
            default => 'other',
        };
    }

    /**
     * Every "<prefix>:<id>" kind config('counts.detailed') allows: its prefix with each real id (the
     * Plus courses, the free course's lessons), read from the course files once per request.
     *
     * @return list<string>
     */
    public static function detailedKinds(): array
    {
        static $kinds = null;

        if ($kinds !== null) {
            return $kinds;
        }

        // Built from the course files only when they change: kept as a PHP file opcache holds, keyed by the files'
        // dates and sizes, as counts.create is called on every app's every few minutes.
        $file = storage_path('framework/cache/count-kinds-'.self::courseFingerprint().'.php');

        if (is_file($file)) {
            /** @var list<string> $cached */
            $cached = require $file;

            return $kinds = $cached;
        }

        // A song's course (web/course/songs) counts its lessons as a Plus course's do.
        $ids = [
            'plus_courses' => [...self::plusCourses(), ...self::songCourses()],
            'free_lessons' => self::freeLessons(),
            'free_steps' => self::freeSteps(),
            'features' => array_values(array_map('strval', (array) config('counts.features'))),
            'tools' => array_values(array_map('strval', (array) config('tools.slugs'))),
            'breadth' => array_map('strval', range(0, count((array) config('counts.features')))),
            'shares' => self::shares(),
            'course_lessons' => array_keys(self::courseLessons()),
            'course_steps' => array_merge(...array_map(static fn (string $id, int $steps): array => array_map(static fn (int $i): string => $id.'.'.$i, $steps > 0 ? range(0, $steps - 1) : []), array_keys(self::courseLessons()), self::courseLessons())),
        ];
        $kinds = [];

        foreach ((array) config('counts.detailed') as $prefix => $source) {
            foreach ($ids[$source] ?? [] as $id) {
                $kinds[] = $prefix.':'.$id;
            }
        }

        // Written whole, then moved into place, so a request never reads half a file.
        if (is_dir(dirname($file)) || @mkdir(dirname($file), 0o775, true)) {
            $tmp = $file.'.'.bin2hex(random_bytes(4));
            if (file_put_contents($tmp, '<?php return '.var_export($kinds, true).";\n") !== false) {
                rename($tmp, $file);
            }
        }

        return $kinds;
    }

    /**
     * What the detailed kinds are built from (the course files, the counts config and the tools), as a short hash.
     */
    private static function courseFingerprint(): string
    {
        $root = dirname(base_path()).'/web/course';
        $paths = [$root.'/course.json', $root.'/plus/courses.json', $root.'/songs/index.json', ...glob($root.'/plus/*/*.json') ?: [], ...glob($root.'/songs/*.json') ?: []];
        $stamp = array_map(static fn (string $path): string => $path.':'.(is_file($path) ? filemtime($path).':'.filesize($path) : '-'), $paths);

        return substr(hash('sha256', implode('|', $stamp).json_encode([config('counts.detailed'), config('counts.features'), config('counts.share_surfaces'), config('counts.share_methods'), config('tools.slugs')])), 0, 16);
    }

    /**
     * @return list<string>
     */
    private static function plusCourses(): array
    {
        $file = dirname(base_path()).'/web/course/plus/courses.json';
        $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return array_values(array_filter(array_map(static fn (mixed $c): ?string => is_array($c) && is_string($c['id'] ?? null) ? $c['id'] : null, is_array($list) ? (array) ($list['courses'] ?? []) : [])));
    }

    /**
     * The song courses' ids (web/course/songs/index.json), staff-only ones too.
     *
     * @return list<string>
     */
    public static function songCourses(): array
    {
        $file = dirname(base_path()).'/web/course/songs/index.json';
        $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return array_values(array_filter(array_map(static fn (mixed $c): ?string => is_array($c) && is_string($c['id'] ?? null) ? $c['id'] : null, is_array($list) ? (array) ($list['courses'] ?? []) : [])));
    }

    /**
     * @return list<string>
     */
    private static function freeLessons(): array
    {
        $file = dirname(base_path()).'/web/course/course.json';
        $course = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $ids = [];

        foreach (is_array($course) ? (array) ($course['units'] ?? []) : [] as $unit) {
            foreach (is_array($unit) ? (array) ($unit['lessons'] ?? []) : [] as $lesson) {
                if (is_array($lesson) && is_string($lesson['id'] ?? null)) {
                    $ids[] = $lesson['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * Every Plus and song course's lessons, each with as many steps as its longest form (its own or a variant's).
     *
     * @return array<string, int>
     */
    private static function courseLessons(): array
    {
        static $lessons = null;

        if ($lessons !== null) {
            return $lessons;
        }

        $root = dirname(base_path()).'/web/course';
        $files = [];

        foreach (['plus/courses.json' => 'plus', 'songs/index.json' => 'songs'] as $list => $folder) {
            $index = is_file($root.'/'.$list) ? json_decode((string) file_get_contents($root.'/'.$list), true) : null;

            foreach (is_array($index) ? (array) ($index['courses'] ?? []) : [] as $course) {
                if (is_array($course) && is_string($course['file'] ?? null) && ! str_contains($course['file'], '..')) {
                    $files[] = $root.'/'.$folder.'/'.$course['file'];
                }
            }
        }

        $lessons = [];

        foreach ($files as $file) {
            $course = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            foreach (is_array($course) ? (array) ($course['units'] ?? []) : [] as $unit) {
                foreach (is_array($unit) ? (array) ($unit['lessons'] ?? []) : [] as $lesson) {
                    if (! is_array($lesson) || ! is_string($lesson['id'] ?? null)) {
                        continue;
                    }

                    $steps = count((array) ($lesson['steps'] ?? []));

                    foreach ((array) ($lesson['variants'] ?? []) as $variant) {
                        $steps = max($steps, is_array($variant) ? count((array) ($variant['steps'] ?? [])) : 0);
                    }

                    $lessons[$lesson['id']] = $steps;
                }
            }
        }

        return $lessons;
    }

    /**
     * Every "<surface>.<method>" a share may be: each surface (a free tool's as tool-<slug>) with each method.
     *
     * @return list<string>
     */
    private static function shares(): array
    {
        $surfaces = [...(array) config('counts.share_surfaces'), ...array_map(static fn (mixed $slug): string => 'tool-'.$slug, (array) config('tools.slugs'))];
        $ids = [];

        foreach ($surfaces as $surface) {
            foreach ((array) config('counts.share_methods') as $method) {
                $ids[] = $surface.'.'.$method;
            }
        }

        return $ids;
    }

    /**
     * Each free-course lesson's steps, "<lessonId>.<step>", as many as its longest form has (its own
     * steps or an experiment's variant).
     *
     * @return list<string>
     */
    private static function freeSteps(): array
    {
        $file = dirname(base_path()).'/web/course/course.json';
        $course = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $ids = [];

        foreach (is_array($course) ? (array) ($course['units'] ?? []) : [] as $unit) {
            foreach (is_array($unit) ? (array) ($unit['lessons'] ?? []) : [] as $lesson) {
                if (! is_array($lesson) || ! is_string($lesson['id'] ?? null)) {
                    continue;
                }

                $steps = count((array) ($lesson['steps'] ?? []));

                foreach ((array) ($lesson['variants'] ?? []) as $variant) {
                    $steps = max($steps, is_array($variant) ? count((array) ($variant['steps'] ?? [])) : 0);
                }

                for ($i = 0; $i < $steps; $i++) {
                    $ids[] = $lesson['id'].'.'.$i;
                }
            }
        }

        return $ids;
    }

    /**
     * One of the server's own kinds (config counts.server_kinds), added to today's count: a number
     * and nothing else.
     */
    public function server(string $kind, bool $signedIn, ?string $country): void
    {
        if (config('counts.enabled') !== true || ! in_array($kind, (array) config('counts.server_kinds'), true)) {
            return;
        }

        DB::statement('insert into usage_counts (day, kind, audience, country, count) values (?, ?, ?, ?, 1) on duplicate key update count = count + 1', [ReportDay::today(), $kind, $signedIn ? 'account' : 'guest', $country ?? '']);
        self::hourly($signedIn ? 'account' : 'guest', [[$kind, '', 1]]);
    }

    /**
     * The same counts in this UTC hour (usage_count_hours), in one statement: what a pro-rated
     * comparison reads (today by this hour against yesterday by the same hour, and every period
     * cut at the same point). Never a country or an arm.
     *
     * @param  list<array{0: string, 1: string, 2: int}>  $rows  [kind, source, n]
     */
    private static function hourly(string $audience, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $hour = now()->utc()->startOfHour()->format('Y-m-d H:i:s');
        $values = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?)'));
        $bindings = array_merge(...array_map(static fn (array $row): array => [$hour, $row[0], $audience, $row[1], $row[2]], $rows));
        DB::statement("insert into usage_count_hours (hour, kind, audience, source, count) values {$values} as new on duplicate key update count = usage_count_hours.count + new.count", $bindings);
    }

    /**
     * The admin's Usage panel: each day's counts, the totals per kind, and the countries, over the
     * last `$days` days.
     *
     * @return array{days: list<array{date: string, kind: string, guest: int, account: int}>, countries: list<array{country: string|null, count: int}>, totals: list<array{kind: string, guest: int, account: int}>}
     */
    public function summary(int $days): array
    {
        $since = ReportDay::now()->subDays($days - 1)->toDateString();
        $base = static fn () => DB::table('usage_counts')->where('day', '>=', $since);
        $split = "sum(case when audience = 'guest' then count else 0 end) as guest, sum(case when audience = 'account' then count else 0 end) as account";

        return [
            'days' => array_values($base()->selectRaw("day, kind, {$split}")->groupBy('day', 'kind')->orderBy('day')->orderBy('kind')->get()
                ->map(static fn (object $r): array => ['date' => (string) $r->day, 'kind' => (string) $r->kind, 'guest' => (int) $r->guest, 'account' => (int) $r->account])->all()),
            'countries' => array_values($base()->selectRaw('country, sum(count) as total')->groupBy('country')->orderByDesc('total')->get()
                ->map(static fn (object $r): array => ['country' => $r->country !== '' ? (string) $r->country : null, 'count' => (int) $r->total])->all()),
            'totals' => array_values($base()->selectRaw("kind, {$split}")->groupBy('kind')->orderBy('kind')->get()
                ->map(static fn (object $r): array => ['kind' => (string) $r->kind, 'guest' => (int) $r->guest, 'account' => (int) $r->account])->all()),
        ];
    }
}
