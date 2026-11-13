<?php

declare(strict_types=1);

namespace App\Support\Live;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The live view's heartbeats (docs/realtime-dashboard-plan.md; Hillel, 2026-11-12: "Allow, cache only"): each beat adds
 * to anonymous counts for its minute, held in the file cache for half an hour and never stored. No ID, no address, no
 * cookie: a minute is just how many beats and page views each area and page had. A staff device's beats are counted
 * apart, for ?admins=1.
 */
final class LiveBeats
{
    /** The areas a beat may name: the app's (from its router) and the public pages'. */
    public const AREAS = ['courses', 'studio', 'app', 'site', 'tools'];

    /** Minutes kept, the chart's 30 and a little more. */
    public const MINUTES = 35;

    /** Distinct pages a minute keeps by name; the rest are "other", so a flood of made-up names can't grow it. */
    public const PAGES = 60;

    public function record(string $area, string $page, bool $view, bool $staff, ?Carbon $at = null): void
    {
        // A beat naming an unknown area or a page no screen has counts nothing.
        if (! in_array($area, self::AREAS, true) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_\/-]{0,60}$/', $page) !== 1) {
            return;
        }
        $minute = self::minute($at ?? now());
        $key = 'live:'.$minute;

        $add = function () use ($key, $area, $page, $view, $staff): void {
            /** @var array<string, int|array<string, int>> $counts */
            $counts = (array) self::store()->get($key, []);
            $who = $staff ? 's' : 'p';
            self::add($counts, $who.'b');
            self::add($counts, $who.'b:'.$area);
            if ($view) {
                self::add($counts, $who.'v');
                self::add($counts, $who.'v:'.$area);
                $pages = (array) ($counts['pages'] ?? []);
                $name = $area.'|'.$page;
                if (! isset($pages[$name]) && count($pages) >= self::PAGES) {
                    $name = $area.'|other';
                }
                $pages[$name] = (int) ($pages[$name] ?? 0) + 1;
                $counts['pages'] = $pages;
            }
            self::store()->put($key, $counts, now()->addMinutes(self::MINUTES));
        };

        // One beat at a time for a minute, so two at once never lose one.
        $locks = self::store()->getStore();
        $locks instanceof LockProvider ? $locks->lock($key.':lock', 2)->block(2, $add) : $add();
    }

    /**
     * A minute's counts: `pb`/`sb` beats (the public's and staff's), `pv`/`sv` views, each also `:area`, and `pages`
     * (`area|page` => views, the public's and staff's together).
     *
     * @return array<string, mixed>
     */
    public function minuteCounts(Carbon $at): array
    {
        return (array) self::store()->get('live:'.self::minute($at), []);
    }

    public static function minute(Carbon $at): string
    {
        return $at->copy()->utc()->format('YmdHi');
    }

    /** @param  array<string, mixed>  $counts */
    private static function add(array &$counts, string $key): void
    {
        $counts[$key] = (int) ($counts[$key] ?? 0) + 1;
    }

    /** The file cache (never the database); the tests take their own in-memory one, as their workers share the files. */
    private static function store(): Repository
    {
        return Cache::store((string) config('counts.live_store', 'file'));
    }
}
