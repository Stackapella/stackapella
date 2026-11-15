<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\Hosts;
use App\Support\Languages\SiteLanguages;
use Illuminate\Http\Request;

/**
 * A public page's visit, counted once a page load by its section only (sv_<section>): the shared footer
 * (components/site/footer.blade.php) carries the kind and js/public/site.js sends it, no source, no id and
 * no path beyond the section (CLAUDE.md, no tracking). Never on the app's host, and nothing while counts are
 * off. site_visit stays the landing's own, with its ?ref= source.
 */
final class SiteVisit
{
    /** Each section's kind is sv_<section> (config counts.kinds). */
    public const SECTIONS = ['home', 'courses', 'course', 'lesson', 'tools', 'songs', 'part', 'guides', 'other'];

    /**
     * Trial pages (a design Hillel is comparing), by path: each counts as its own kind, sv_<value>, and is no section,
     * so the growth totals and comparisons (which read SECTIONS) never include it.
     */
    public const TRIALS = ['next-grok' => 'next_grok'];

    /** The section a public path belongs to (as Request::path() gives it: "/" for the home, else no leading slash). */
    public static function section(string $path): string
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));

        return match (true) {
            $parts === [] => 'home',
            // A candidate landing page, counted apart and in no growth read (it isn't one of SECTIONS).
            in_array(implode('/', $parts), ['next-claude', 'es/next-claude'], true) => 'next_claude',
            $parts[0] === 'courses' => match (count($parts)) {
                1 => 'courses',
                2 => 'course',
                default => 'lesson',
            },
            $parts[0] === 'tools' => 'tools',
            // A song's part page (songs/<song>/<part>) apart from the songbook and the song's own page,
            // so its plays (part_play) can be read against its loads.
            $parts[0] === 'songs' && count($parts) === 3 => 'part',
            in_array($parts[0], ['songs', 'choirs'], true) => 'songs',
            $parts[0] === 'guides' => 'guides',
            default => 'other',
        };
    }

    /** The count this page load sends, or null: counts off, or the app's host (when the site and the app are apart). */
    public static function kind(Request $request): ?string
    {
        if (config('counts.enabled') !== true || (Hosts::split() && ! Hosts::onPublicHost($request))) {
            return null;
        }

        $path = trim(SiteLanguages::englishPath('/'.ltrim($request->path(), '/'), app()->getLocale()), '/');

        return 'sv_'.(self::TRIALS[$path] ?? self::section($request->path()));
    }
}
