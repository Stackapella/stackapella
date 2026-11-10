<?php

declare(strict_types=1);

namespace App\Support\Ops;

use App\Models\Setting;

/**
 * What the weekly sitemap check found (app:sitemap:check --save, Wednesdays, before Hillel's search
 * review): every page search engines can't index, and why, one line each. Readiness and admin's
 * overview read it; the next clean run clears it.
 */
final class SitemapProblems
{
    public const KEY = 'ops.sitemap_check';

    /** Lines shown in admin's overview, each cut short, the rest counted. */
    private const SHOWN = 5;

    /**
     * @param  list<string>  $problems
     */
    public static function save(string $base, array $problems): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => [
            'at' => now()->utc()->toIso8601ZuluString(),
            'base' => $base,
            'problems' => $problems,
        ]]);
    }

    /**
     * @return array{at: string, problems: list<string>}|null null before the first run here
     */
    public static function latest(): ?array
    {
        $value = Setting::query()->where('key', self::KEY)->first()?->value;

        if (! is_array($value) || ! is_string($value['at'] ?? null)) {
            return null;
        }

        $problems = is_array($value['problems'] ?? null) ? $value['problems'] : [];

        return ['at' => $value['at'], 'problems' => array_values(array_filter($problems, is_string(...)))];
    }

    /**
     * The first few, each cut at 200 characters, and how many more there are.
     *
     * @return list<string>
     */
    public static function brief(): array
    {
        $problems = self::latest()['problems'] ?? [];
        $shown = array_map(static fn (string $line): string => mb_strimwidth($line, 0, 200, '...'), array_slice($problems, 0, self::SHOWN));

        return count($problems) > self::SHOWN ? [...$shown, 'and '.(count($problems) - self::SHOWN).' more (php artisan app:sitemap:check --all)'] : $shown;
    }
}
