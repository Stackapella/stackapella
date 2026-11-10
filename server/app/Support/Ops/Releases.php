<?php

declare(strict_types=1);

namespace App\Support\Ops;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The deploys, as the scheduler sees them: each commit it runs (config app.build, App Platform's
 * APP_BUILD) and when it first ran it. The heartbeat stamps it every minute; the database is asked
 * at most once an hour per commit. A container that doesn't know its commit stamps nothing.
 */
final class Releases
{
    public static function stamp(): void
    {
        $build = (string) config('app.build');

        if (preg_match('/^[0-9a-f]{7,40}$/', $build) !== 1 || ! Cache::add('releases:seen:'.$build, true, 3600)) {
            return;
        }

        DB::table('releases')->insertOrIgnore(['build' => $build, 'first_seen_at' => now()]);
    }

    /**
     * The latest deploys, newest first.
     *
     * @return list<array{commit: string, firstSeenAt: string}>
     */
    public static function recent(int $limit = 60): array
    {
        return array_values(DB::table('releases')->orderByDesc('first_seen_at')->orderByDesc('id')->limit($limit)->get(['build', 'first_seen_at'])
            ->map(static fn (object $r): array => ['commit' => (string) $r->build, 'firstSeenAt' => Carbon::parse((string) $r->first_seen_at)->utc()->toIso8601ZuluString()])->all());
    }
}
