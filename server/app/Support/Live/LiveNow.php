<?php

declare(strict_types=1);

namespace App\Support\Live;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * admin.live.show (docs/realtime-dashboard-plan.md): who is here right now, page views by the minute, and each area's
 * busiest pages, from the heartbeats' minute counts (LiveBeats) and the sessions the app already keeps. Numbers only.
 */
final class LiveNow
{
    public const REFRESH_SECONDS = 15;

    /** The chart's minutes, the one so far last. */
    public const MINUTES = 30;

    public function __construct(private readonly LiveBeats $beats) {}

    /**
     * @return array{at: string, refreshSeconds: int, now: array{total: int, signedIn: int, guests: int}, views: array{perMinute: list<int>, last30: int}, areas: list<array{key: string, now: int, views30: int, top: list<array{page: string, views: int}>}>}
     */
    public function show(bool $admins, ?Carbon $at = null): array
    {
        $at ??= now();
        $minutes = [];
        for ($i = self::MINUTES - 1; $i >= 0; $i--) {
            $minutes[] = $this->beats->minuteCounts($at->copy()->subMinutes($i));
        }
        // "Right now" is the last whole minute: an app beats every 30 seconds, so two beats are one person.
        $last = $this->beats->minuteCounts($at->copy()->subMinute());
        $count = static fn (array $minute, string $key): int => (int) ($minute['p'.$key] ?? 0) + ($admins ? (int) ($minute['s'.$key] ?? 0) : 0);

        $signedIn = $this->signedIn($at, $admins);
        $guests = max(0, (int) round($count($last, 'b') / 2) - $signedIn);

        $perMinute = array_map(static fn (array $minute): int => $count($minute, 'v'), $minutes);
        $areas = [];
        foreach (LiveBeats::AREAS as $area) {
            $pages = [];
            foreach ($minutes as $minute) {
                foreach ((array) ($minute['pages'] ?? []) as $name => $views) {
                    [$inArea, $page] = explode('|', (string) $name, 2) + [1 => 'other'];
                    if ($inArea === $area) {
                        $pages[$page] = ($pages[$page] ?? 0) + (int) $views;
                    }
                }
            }
            arsort($pages);
            $areas[] = [
                'key' => $area,
                'now' => (int) round($count($last, 'b:'.$area) / 2),
                'views30' => (int) array_sum(array_map(static fn (array $minute): int => $count($minute, 'v:'.$area), $minutes)),
                'top' => array_map(static fn (string $page, int $views): array => ['page' => $page, 'views' => $views], array_slice(array_keys($pages), 0, 3), array_slice(array_values($pages), 0, 3)),
            ];
        }
        usort($areas, static fn (array $a, array $b): int => [$b['now'], $b['views30'], $a['key']] <=> [$a['now'], $a['views30'], $b['key']]);

        return [
            'at' => $at->copy()->utc()->toIso8601ZuluString(),
            'refreshSeconds' => self::REFRESH_SECONDS,
            'now' => ['total' => $signedIn + $guests, 'signedIn' => $signedIn, 'guests' => $guests],
            'views' => ['perMinute' => $perMinute, 'last30' => (int) array_sum($perMinute)],
            'areas' => $areas,
        ];
    }

    /** Accounts with a session active in the last 2 minutes (the database sessions production keeps; empty elsewhere). */
    private function signedIn(Carbon $at, bool $admins): int
    {
        return (int) DB::table((string) config('session.table', 'sessions'))
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->where('sessions.last_activity', '>=', $at->copy()->subMinutes(2)->getTimestamp())
            ->when(! $admins, fn ($query) => $query->where('users.is_admin', false))
            ->distinct()->count('sessions.user_id');
    }
}
