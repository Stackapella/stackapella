<?php

declare(strict_types=1);

namespace App\Support\Ops;

use App\Models\Setting;

/**
 * What the last song sync couldn't load (app:templates:sync), kept so a deploy that skipped a
 * song says so: the Readiness check, admin's overview and /up (which deploy-watch prints) read it,
 * and the next clean sync clears it. Sakura Sakura's notes changed under a published version on
 * 2026-11-09 and the sync's failure rolled back every deploy until it was fixed; since then the
 * sync skips the song, keeps its live version, and the problem shows here instead.
 */
final class SyncProblems
{
    public const KEY = 'templates.sync_problems';

    /**
     * @param  list<string>  $problems
     */
    public static function save(array $problems): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => [
            'at' => now()->utc()->toIso8601ZuluString(),
            'build' => config('app.build'),
            'problems' => $problems,
        ]]);
    }

    /**
     * The last sync's problems, none when it was clean or hasn't run here.
     *
     * @return list<string>
     */
    public static function latest(): array
    {
        $value = Setting::query()->where('key', self::KEY)->first()?->value;
        $problems = is_array($value) ? ($value['problems'] ?? []) : [];

        return is_array($problems) ? array_values(array_filter($problems, is_string(...))) : [];
    }

    /**
     * For /up, which anyone can read: the first few, each cut short. The songs and the rules are
     * public (the content repo is), so naming them gives nothing away.
     *
     * @return list<string>
     */
    public static function brief(): array
    {
        return array_map(static fn (string $line): string => mb_strimwidth($line, 0, 200, '...'), array_slice(self::latest(), 0, 5));
    }
}
