<?php

declare(strict_types=1);

namespace App\Actions\Counts;

use Illuminate\Support\Facades\DB;

/**
 * Adds a request's events to today's counts (config/counts.php): only the kinds on the list, each
 * capped, and nothing kept but the numbers.
 */
final class RecordCounts
{
    /** Kinds that keep where the device came from (with the funnel's): the landing's visits. */
    public const SOURCED = ['site_visit'];

    /**
     * A funnel or ask kind (Funnel::kinds()) also keeps the device's experiment arm, when it names
     * a configured one, and a funnel kind or a landing visit its source, grouped (self::source());
     * either one, and the row keeps no country, so those cells stay coarse.
     *
     * @param  list<array{kind: string, n?: int}>  $events
     */
    public function handle(array $events, bool $signedIn, ?string $country, ?string $variant = null, ?string $ref = null): void
    {
        $funnel = Funnel::kinds();
        $kinds = [...(array) config('counts.kinds'), ...$funnel, ...self::detailedKinds()];
        $variant = $variant !== null && Funnel::knownVariant($variant) ? $variant : '';
        $source = self::source($ref);
        $max = (int) config('counts.max_n');
        $totals = [];

        foreach (array_slice($events, 0, (int) config('counts.max_events')) as $event) {
            if (in_array($event['kind'], $kinds, true)) {
                // A funnel kind is a device's first: once a request, whatever `n` says.
                $cap = in_array($event['kind'], $funnel, true) ? 1 : $max;
                $totals[$event['kind']] = min($cap, ($totals[$event['kind']] ?? 0) + max(1, min($cap, (int) ($event['n'] ?? 1))));
            }
        }

        $day = now()->toDateString();
        $audience = $signedIn ? 'account' : 'guest';

        foreach ($totals as $kind => $n) {
            $arm = in_array($kind, $funnel, true) ? $variant : '';
            $from = in_array($kind, $funnel, true) || in_array($kind, self::SOURCED, true) ? $source : '';
            DB::statement('insert into usage_counts (day, kind, audience, country, variant, source, count) values (?, ?, ?, ?, ?, ?, ?) on duplicate key update count = count + ?', [$day, $kind, $audience, $arm !== '' || $from !== '' ? '' : ($country ?? ''), $arm, $from, $n, $n]);
        }
    }

    /**
     * Where a device came from, from its ?ref= tag, grouped: a campaign's tag as written (config
     * counts.sources), "harmony" for a shared harmony's (h-*), "coach" for a coach's link (c-*),
     * "other" for any other, and '' for none. Never the tag itself, so no id is kept.
     */
    public static function source(?string $ref): string
    {
        $ref = strtolower(trim((string) $ref));

        return match (true) {
            $ref === '' => '',
            in_array($ref, (array) config('counts.sources'), true) => $ref,
            str_starts_with($ref, 'h-') => 'harmony',
            str_starts_with($ref, 'c-') => 'coach',
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

        $ids = ['plus_courses' => self::plusCourses(), 'free_lessons' => self::freeLessons()];
        $kinds = [];

        foreach ((array) config('counts.detailed') as $prefix => $source) {
            foreach ($ids[$source] ?? [] as $id) {
                $kinds[] = $prefix.':'.$id;
            }
        }

        return $kinds;
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
     * One of the server's own kinds (config counts.server_kinds), added to today's count: a number
     * and nothing else.
     */
    public function server(string $kind, bool $signedIn, ?string $country): void
    {
        if (config('counts.enabled') !== true || ! in_array($kind, (array) config('counts.server_kinds'), true)) {
            return;
        }

        DB::statement('insert into usage_counts (day, kind, audience, country, count) values (?, ?, ?, ?, 1) on duplicate key update count = count + 1', [now()->toDateString(), $kind, $signedIn ? 'account' : 'guest', $country ?? '']);
    }

    /**
     * The admin's Usage panel: each day's counts, the totals per kind, and the countries, over the
     * last `$days` days.
     *
     * @return array{days: list<array{date: string, kind: string, guest: int, account: int}>, countries: list<array{country: string|null, count: int}>, totals: list<array{kind: string, guest: int, account: int}>}
     */
    public function summary(int $days): array
    {
        $since = now()->subDays($days - 1)->toDateString();
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
