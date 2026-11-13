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

    /**
     * A song's page: the app, Home (a song page's Learn opens Home with the song's sheet, ?song=) and the sheet's own code,
     * which main.js fetches at start on such a link (b102f383d; on a slow 4G phone it was the gap before the sheet, frontend2).
     *
     * @return list<string>
     */
    public static function song(): array
    {
        return self::urls([...self::list(''), ...self::list('home '), '/js/main.js', '/js/components/dialogs.js', '/js/views/template.js']);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function urls(array $paths): array
    {
        // On the app's host where the site and the app are apart (production), whose static files answer any origin
        // (.do/nginx.conf), so the crossorigin prefetch isn't blocked: it was, with a console error each, before they did
        // (2026-11-13; server/tool/split-hosts.sh checks it). Else this page's own (a local server, maybe another port).
        $split = Hosts::split();

        return array_values(array_unique(array_map(static fn (string $p): string => $split ? Hosts::appLink($p) : $p, $paths)));
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
