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
        'spirituals' => ['spiritual'],
        'sea-shanties' => ['sea shanty'],
        'rounds' => ['round'],
        'jewish-songs' => ['jewish'],
        // Only what's a folk song: Happy Birthday and Morley's madrigal are under /songs's folk group, not on its page.
        'folk-songs' => ['folk'],
        // Not With Cat-like Tread: a comic opera chorus is neither barbershop nor parlour (the coordinator, 2026-11-12).
        'barbershop-songs' => ['barbershop', 'parlour song', 'early popular song'],
    ];

    /**
     * /songs in groups (the coordinator, 2026-11-10, as the library passed 40 open songs): each song in the first group,
     * in this order, that one of its genres names, so a Christmas hymn sits with the Christmas songs and a round with the
     * rounds; a song in none is under "more".
     */
    public const GROUPS = [
        'christmas' => ['christmas', 'carol'],
        // Before folk: the Jewish songs are traditional too, and their page is theirs (2026-11-11).
        'jewish-songs' => ['jewish'],
        'rounds' => ['round'],
        'spirituals' => ['spiritual'],
        'hymns' => ['hymn', 'gospel'],
        'sea-shanties' => ['sea shanty'],
        'folk-songs' => ['folk', 'traditional', 'celebration'],
        'classical' => ['classical', 'latin', 'choral'],
        'barbershop-songs' => ['barbershop', 'parlour song', 'early popular song', 'musical theatre'],
    ];

    /**
     * Collections that list only the songs /songs groups under them: "folk" also tags shanties, rounds, hymns and a
     * carol, which keep their own pages, so Folk songs lists the 10 that are only folk (the coordinator, 2026-11-12).
     */
    public const OWN_GROUP_ONLY = ['folk-songs', 'barbershop-songs'];

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
        return $this->cards($this->ordered($slug));
    }

    /**
     * A collection's open songs' slugs in its page's order (by title), without building their cards: /songs samples a
     * few of each and builds only those (a card plays its first line, so building every one cost /songs about 70 ms).
     *
     * @return list<string>
     */
    public function ordered(string $slug): array
    {
        $members = $this->members($slug);

        return array_values($this->parts->songs()
            ->filter(static fn (Template $template): bool => in_array((string) $template->slug, $members, true))
            ->sortBy(static fn (Template $template): string => (string) $template->title, SORT_NATURAL | SORT_FLAG_CASE)
            ->map(static fn (Template $template): string => (string) $template->slug)
            ->all());
    }

    /**
     * These open songs' cards, in the order given (a slug that isn't an open song with pages is left out).
     *
     * @param  list<string>  $slugs
     * @return list<array{slug: string, title: string, url: string, from: string|null, line: array<string, mixed>|null, meta: list<string>, parts: list<array{key: string, name: string, melody: bool, lowName: string, highName: string, url: string}>}>
     */
    public function cards(array $slugs): array
    {
        $cards = [];
        foreach ($this->parts->songs() as $template) {
            if (in_array((string) $template->slug, $slugs, true)) {
                $cards[(string) $template->slug] = Carols::card($template);
            }
        }

        return array_values(array_filter(array_map(static fn (string $slug): ?array => $cards[$slug] ?? null, $slugs)));
    }

    /**
     * A collection's open songs' slugs, or [] when it isn't a page: what songs() lists, without building the cards
     * (each plays a first line), read once a request for the song pages' links.
     *
     * @return list<string>
     */
    public function members(string $slug): array
    {
        if (! isset(self::COLLECTIONS[$slug]) || ! trans()->has('site.collections.'.$slug) || ! SongParts::open()) {
            return [];
        }

        $members = $this->members[$slug] ??= array_values($this->parts->songs()
            ->filter(static fn (Template $template): bool => self::collects($slug, $template))
            ->map(static fn (Template $template): string => (string) $template->slug)
            ->all());

        return count($members) >= self::MIN ? $members : [];
    }

    /** @var array<string, list<string>> */
    private array $members = [];

    /**
     * The page that collects a song, for its own page's and its part pages' trail and link (the coordinator,
     * 2026-11-10: each collection linked from its songs): the first of /songs's groups, in order, whose page lists it,
     * the carols page only while the carols are linked (Carols::linked), or null.
     *
     * @return array{path: string, name: string, more: string}|null
     */
    public function home(string $song): ?array
    {
        foreach (array_keys(self::GROUPS) as $key) {
            if ($key === 'christmas') {
                if (Carols::linked() && Carols::has($song)) {
                    return ['path' => Carols::PATH, 'name' => (string) __('site.carols.crumb'), 'more' => (string) __('site.carols.links.part')];
                }
            } elseif (in_array($song, $this->members($key), true)) {
                return ['path' => self::path($key), 'name' => (string) __('site.collections.'.$key.'.crumb'), 'more' => (string) __('site.collections.'.$key.'.more')];
            }
        }

        return null;
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
            array_filter(self::slugs(), fn (string $slug): bool => $this->members($slug) !== []),
        ));
    }

    /** A collection's lastmod for the sitemap: the newest of its songs' current versions, or null. */
    public function lastModified(string $slug): ?string
    {
        $newest = null;
        foreach ($this->parts->songs() as $template) {
            $when = $template->currentVersion->created_at ?? null;
            if (isset(self::COLLECTIONS[$slug]) && self::collects($slug, $template) && $when !== null && ($newest === null || $when->greaterThan($newest))) {
                $newest = $when;
            }
        }

        return $newest !== null ? Carbon::parse($newest)->utc()->toDateString() : null;
    }

    /**
     * The /songs group a song's genres put it in (GROUPS, first match in order), or "more".
     *
     * @param  list<string>  $genres
     */
    public static function group(array $genres): string
    {
        $own = array_map(static fn (string $g): string => strtolower(trim($g)), $genres);
        foreach (self::GROUPS as $key => $names) {
            if (array_intersect($own, $names) !== []) {
                return $key;
            }
        }

        return 'more';
    }

    /**
     * The songs, in /songs's groups: Christmas first in the carol season and last otherwise, then the rest in order,
     * each with the page that collects it when there is one (the carols page only in its season); empty groups left out.
     *
     * @param  list<array<string, mixed>>  $songs  SongPage::of()s
     * @return list<array{key: string, songs: list<array<string, mixed>>, link: string|null}>
     */
    public function grouped(array $songs): array
    {
        $order = array_keys(self::GROUPS);
        $order = Carols::inSeason() ? $order : [...array_values(array_diff($order, ['christmas'])), 'christmas'];
        $open = array_column($this->open(), 'path', 'slug');

        $groups = [];
        foreach ([...$order, 'more'] as $key) {
            $in = array_values(array_filter($songs, static fn (array $song): bool => self::group(array_values(array_filter((array) ($song['genre'] ?? []), 'is_string'))) === $key));
            if ($in !== []) {
                $groups[] = ['key' => $key, 'songs' => $in, 'link' => $key === 'christmas' ? (Carols::inSeason() && app(Carols::class)->slugs() !== [] ? Carols::PATH : null) : ($open[$key] ?? null)];
            }
        }

        return $groups;
    }

    /** Whether a collection's page lists a song: one of its genres, and for OWN_GROUP_ONLY its /songs group too. */
    private static function collects(string $slug, Template $template): bool
    {
        $own = array_map(static fn (string $g): string => strtolower(trim($g)), array_values(array_filter((array) (((array) $template->currentVersion?->data)['genre'] ?? []), 'is_string')));

        return array_intersect($own, self::COLLECTIONS[$slug]) !== []
            && (! in_array($slug, self::OWN_GROUP_ONLY, true) || self::group($own) === $slug);
    }
}
