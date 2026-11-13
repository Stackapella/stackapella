<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\Hosts;

/**
 * What the landing and a song's page fetch for the app while the visitor reads (docs/first-session-review-2026-11-13.md,
 * item 3: "Start singing" and a song's sheet open from the cache). The app's own lists, as tool/preload_list.py writes
 * them into web/js/preload.js, so they never drift from what the app loads; on the app's host.
 */
final class AppPrefetch
{
    /** @return list<string> Start singing: the app, lesson 1's first screen, its words and the courses */
    public static function landing(): array
    {
        return self::urls([...self::list(''), ...self::list('learn '), '/js/main.js', '/locales/lesson1/en.json', '/course/course.json']);
    }

    /** @return list<string> A song's page: the app and the song's sheet */
    public static function song(): array
    {
        return self::urls([...self::list(''), '/js/main.js', '/js/views/template.js']);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function urls(array $paths): array
    {
        // Not where the site and the app are on two hosts (production): the app's files answer no CORS header, so a
        // prefetch with crossorigin from the site is blocked and logs a console error (the launch smoke, 2026-11-13).
        // Off there until the app's static files allow the site's origin. One host (a local server): this page's own.
        if (Hosts::split()) {
            return [];
        }

        return array_values(array_unique($paths));
    }

    /**
     * One of preload.js's generated lists, found by preload_list.py's own markers (`// preload_list.py: begin` and `end`,
     * or `learn begin` and `learn end`), which the tool writes between, so they never move. Read once a process for the
     * file's bytes.
     *
     * @return list<string>
     */
    private static function list(string $marker): array
    {
        static $lists = [];
        $path = dirname(base_path()).'/web/js/preload.js';
        $key = $marker.':'.(is_file($path) ? filemtime($path) : 0);
        if (! isset($lists[$key])) {
            $source = is_file($path) ? (string) file_get_contents($path) : '';
            $between = preg_match('#// preload_list\.py: '.preg_quote($marker, '#').'begin\n(.*?)// preload_list\.py: '.preg_quote($marker, '#').'end#s', $source, $m) === 1 ? $m[1] : '';
            preg_match_all("#'(/[\w./-]+)'#", $between, $paths);
            $lists[$key] = array_values(array_unique($paths[1]));
        }

        return $lists[$key];
    }
}
