<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use Illuminate\Support\Carbon;

/**
 * The song collections (/songs/<collection>, docs/search-content-plan.md: pages for what choirs search, each checked for
 * demand first): the open songs whose file's genre names the collection, as cards in the carols page's songbook
 * (Carols::card). A collection is there only with its words (site.collections.<slug>) and at least MIN open songs, so a
 * page is never thin, and a song joins by itself when it opens. The view is marketing's (site.collection).
 */
final class SongCollections
{
    /** Each collection and the genres that put a song in it (a song file's genre, matched without case). */
    public const COLLECTIONS = [
        'hymns' => ['hymn'],
    ];

    /** Fewer open songs than this and the collection isn't a page (it would be thin). */
    public const MIN = 4;

    public const VIEW = 'site.collection';

    public function __construct(private readonly SongParts $parts) {}

    /**
     * The collections' slugs, for the route.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::COLLECTIONS);
    }

    public static function path(string $slug): string
    {
        return '/songs/'.$slug;
    }

    /**
     * A collection's songs as cards, or [] when it isn't a page (no words, too few open songs, the song pages shut).
     *
     * @return list<array{slug: string, title: string, url: string, from: string|null, line: array<string, mixed>|null, meta: list<string>, parts: list<array{key: string, name: string, melody: bool, lowName: string, highName: string, url: string}>}>
     */
    public function songs(string $slug): array
    {
        if (! isset(self::COLLECTIONS[$slug]) || ! trans()->has('site.collections.'.$slug) || ! SongParts::open()) {
            return [];
        }

        $cards = array_values($this->parts->songs()
            ->filter(static fn (Template $template): bool => self::belongs($template, self::COLLECTIONS[$slug]))
            ->map(static fn (Template $template): array => Carols::card($template))
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->all());

        return count($cards) >= self::MIN ? $cards : [];
    }

    /**
     * The collections that are pages now, each with its path and name, for /songs and the sitemap.
     *
     * @return list<array{slug: string, path: string, name: string}>
     */
    public function open(): array
    {
        return array_values(array_map(
            static fn (string $slug): array => ['slug' => $slug, 'path' => self::path($slug), 'name' => (string) __('site.collections.'.$slug.'.crumb')],
            array_filter(self::slugs(), fn (string $slug): bool => $this->songs($slug) !== []),
        ));
    }

    /** A collection's lastmod for the sitemap: the newest of its songs' current versions, or null. */
    public function lastModified(string $slug): ?string
    {
        $newest = null;
        foreach ($this->parts->songs() as $template) {
            $when = $template->currentVersion->created_at ?? null;
            if (isset(self::COLLECTIONS[$slug]) && self::belongs($template, self::COLLECTIONS[$slug]) && $when !== null && ($newest === null || $when->greaterThan($newest))) {
                $newest = $when;
            }
        }

        return $newest !== null ? Carbon::parse($newest)->utc()->toDateString() : null;
    }

    /** @param  list<string>  $genres */
    private static function belongs(Template $template, array $genres): bool
    {
        $own = array_map(static fn (string $g): string => strtolower(trim($g)), array_filter((array) (((array) $template->currentVersion?->data)['genre'] ?? []), 'is_string'));

        return array_intersect($own, $genres) !== [];
    }
}
