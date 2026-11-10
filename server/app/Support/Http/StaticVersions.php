<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * The public pages' render-blocking files, asked for as ?v=<their content hash> so the browser and
 * Cloudflare's edge can keep them a year. nginx gives the year only to the exact hash this image
 * was built with (nginxMap, written at build time into /etc/nginx/static-versions.conf): during a
 * rolling deploy an old container asked for a new hash answers as today (revalidate, never at the
 * edge), so no address is ever kept with the wrong bytes, and a ?v= that matches nothing is never
 * kept either. Pure (no config, no app), so the Docker build can run it before the app boots.
 */
final class StaticVersions
{
    /** The files the public layout links with ?v= (paths under the web root). */
    public const FILES = ['/js/theme-boot.js', '/js/game-css.js', '/css/site.css', '/css/site-game.css', '/css/courses.css'];

    private const LENGTH = 12;

    /** The file's ?v= value: the first 12 hex of its xxh128, or '' when it's missing. */
    public static function of(string $webPath, string $file): string
    {
        $path = rtrim($webPath, '/').$file;

        return is_file($path) ? substr((string) hash_file('xxh128', $path), 0, self::LENGTH) : '';
    }

    /** The file's address with its version (just the file when it has none). */
    public static function url(string $webPath, string $file): string
    {
        $v = self::of($webPath, $file);

        return $v !== '' ? $file.'?v='.$v : $file;
    }

    /**
     * nginx's two maps on "$uri?$arg_v": a year (browser and edge) for each file at this build's
     * hash, and everything else exactly as without one.
     */
    public static function nginxMap(string $webPath): string
    {
        $keys = [];

        foreach (self::FILES as $file) {
            $v = self::of($webPath, $file);

            if ($v !== '') {
                $keys[] = '"'.$file.'?'.$v.'"';
            }
        }

        $map = static function (string $name, string $fallback, string $year) use ($keys): string {
            $lines = array_map(static fn (string $key): string => "    {$key} \"{$year}\";", $keys);

            return "map \"\$uri?\$arg_v\" \${$name} {\n    default \"{$fallback}\";\n".implode("\n", $lines).($lines !== [] ? "\n" : '')."}\n";
        };

        return "# Written by the build (.do/Dockerfile, App\\Support\\Http\\StaticVersions); never edited by hand.\n"
            .$map('static_cache', 'no-cache, max-age=0, must-revalidate', 'public, max-age=31536000, immutable')
            .$map('static_edge', 'no-store', 'public, max-age=31536000');
    }
}
