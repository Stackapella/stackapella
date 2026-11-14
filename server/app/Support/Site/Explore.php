<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Templates\Catalogue;
use Illuminate\Support\Facades\Cache;

/**
 * The five marketing pages' "Explore Stackapella" (docs/marketing-pages-cohesion-plan.md, piece 6; Hillel, 2026-11-14):
 * the other four of /, /songs, /courses, /tools and /pricing, each with its fact, counted from what the site shows
 * (the open songs, the courses in their groups, the listed tools). Off the nexus there's no landing or pricing page,
 * so they're left out there.
 */
final class Explore
{
    public const PAGES = ['home', 'songs', 'courses', 'tools', 'pricing'];

    /** Each page's icon in its tile's bubble (Icons, the design direction's set). */
    private const ICONS = ['home' => 'play', 'songs' => 'chord', 'courses' => 'book', 'tools' => 'tap', 'pricing' => 'plus'];

    /**
     * The ways on from `$current` (one of PAGES): the others, in PAGES order.
     *
     * Each way's fact comes split round its number (`before`, `n`, `after`), so the number can be set bold (calm) or as
     * the tile's big readout (game); `n` is null where the fact has none (pricing).
     *
     * @return list<array{key: string, url: string, name: string, icon: string, before: string, n: ?string, after: string, readout: string}>
     */
    public static function ways(string $current): array
    {
        $counts = [...self::counts(), 'home' => 4];
        $nexus = (bool) config('app.is_nexus', true);
        $urls = ['home' => '/', 'songs' => '/songs', 'courses' => '/courses', 'tools' => SitePages::toolsHome(), 'pricing' => '/pricing'];
        $ways = [];
        foreach (self::PAGES as $key) {
            if ($key === $current || (! $nexus && in_array($key, ['home', 'pricing'], true))) {
                continue;
            }
            $mark = "\u{E000}";
            $fact = (string) __('pages.site.explore.'.$key.'.fact', ['count' => $mark]);
            [$before, $after] = str_contains($fact, $mark) ? explode($mark, $fact, 2) : [$fact, ''];
            $n = str_contains($fact, $mark) ? (string) ($counts[$key] ?? 0) : null;
            $ways[] = [
                'key' => $key,
                'url' => $urls[$key],
                'name' => (string) __('pages.site.explore.'.$key.'.name'),
                'icon' => self::ICONS[$key],
                'before' => $before,
                'n' => $n,
                'after' => $after,
                // The game tile's big readout: the number, or the page's own word where it has none.
                'readout' => $n ?? (string) __('pages.site.explore.'.$key.'.readout'),
            ];
        }

        return $ways;
    }

    /**
     * Each page's count, kept for an hour under the catalogue's fingerprint and the build, as /songs keeps its bands (the
     * landing's is its four voices, written in by ways()).
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $catalogue = app(Catalogue::class);
        $key = 'explore.counts.'.$catalogue->fingerprint().'.'.(string) config('app.build');

        /** @var array<string, int> */
        return Cache::remember($key, now()->addHour(), static fn (): array => [
            'songs' => count($catalogue->openIndex()),
            'courses' => array_sum(array_map(static fn (array $group): int => count($group['courses']), SitePages::courseGroups())),
            'tools' => count(SitePages::tools()),
        ]);
    }
}
