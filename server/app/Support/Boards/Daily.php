<?php

declare(strict_types=1);

namespace App\Support\Boards;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use Carbon\CarbonImmutable;

/**
 * Game mode's daily challenge (Hillel: "i love the game ideas, go go go"): one song part a day, the
 * same for everyone, picked from the date in UTC. The pool is every part of the official songs that
 * are reviewed, free and current, sorted by song then part; the day's is the one at fnv1a32 of
 * "YYYY-MM-DD" modulo the pool's size, so anyone (the app, a test) can work it out.
 */
final class Daily
{
    /** How many past days can still be looked at. */
    public const DAYS_BACK = 7;

    /** A take sung just before midnight still counts for that day for this long after it. */
    public const GRACE_MINUTES = 5;

    /**
     * The day's song and part, or null when the pool is empty.
     *
     * @return array{template: Template, version: TemplateVersion, part: string}|null
     */
    public static function pick(string $date): ?array
    {
        $pool = [];
        $songs = Template::query()->listed()->with('currentVersion')->where('access', TemplateAccess::Free)->whereNull('deleted_at')
            ->whereNotNull('current_version_id')->orderBy('slug')->get();

        foreach ($songs as $template) {
            $version = $template->currentVersion;

            if ($version === null || ! $version->reviewed) {
                continue;
            }

            $parts = array_values(array_filter(array_map(static fn (mixed $p): ?string => is_array($p) && is_string($p['key'] ?? null) ? $p['key'] : null, (array) ($version->data['parts'] ?? []))));
            sort($parts);

            foreach ($parts as $part) {
                $pool[] = ['template' => $template, 'version' => $version, 'part' => $part];
            }
        }

        return $pool === [] ? null : $pool[self::fnv1a32($date) % count($pool)];
    }

    /**
     * FNV-1a, 32 bits, of the date's bytes: the same number the app can work out.
     */
    public static function fnv1a32(string $text): int
    {
        return (int) hexdec(hash('fnv1a32', $text));
    }

    public static function today(): string
    {
        return CarbonImmutable::now('UTC')->toDateString();
    }

    /**
     * Whether a date can be looked at: today or one of the last seven days (UTC).
     */
    public static function viewable(string $date): bool
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

        return $day !== null && $day->format('Y-m-d') === $date && $day->lte(CarbonImmutable::now('UTC')) && $day->gte(CarbonImmutable::now('UTC')->startOfDay()->subDays(self::DAYS_BACK));
    }

    /**
     * Whether a score sung now can go on that date's board: today, or yesterday within a few
     * minutes of midnight.
     */
    public static function open(string $date): bool
    {
        $now = CarbonImmutable::now('UTC');

        return $date === $now->toDateString() || ($date === $now->subDay()->toDateString() && $now->startOfDay()->diffInMinutes($now) < self::GRACE_MINUTES);
    }

    /**
     * When the day's challenge changes: the next midnight, UTC.
     */
    public static function endsAt(string $date): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC')?->addDay()->toIso8601ZuluString() ?? '';
    }
}
