<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use Illuminate\Support\Collection;

/**
 * /songs as a sampler (Hillel, 2026-11-11: "change the /songs page so it samples and then leads to the page for each
 * section, the section style should match the page it leads to"; the design review in the scratchpad's songs-hub): the
 * collection in season as a full-width band in its page's look, each other collection as a panel in its own palette
 * (its `tone`, the same as its page's hero and its link card), each with a few songs to play and the way to all of
 * them, then every song A to Z so none is more than one click away. The view is public/songs.
 */
final class SongsHub
{
    /**
     * Each collection's samples, hand-picked so the page and its shots stay stable (the design review): the best known
     * first, then the easiest, then one to discover. A pick that isn't open is skipped, and the rest of the collection
     * fills the band in its own order.
     */
    public const SAMPLES = [
        'christmas' => ['silent-night', 'jingle-bells', 'hark-the-herald', 'shchedryk'],
        'hymns' => ['amazing-grace', 'it-is-well', 'abide-with-me', 'ode-to-joy'],
        'spirituals' => ['swing-low', 'michael-row', 'wade-in-the-water', 'deep-river'],
        'sea-shanties' => ['drunken-sailor', 'shenandoah', 'spanish-ladies', 'leave-her-johnny'],
        'rounds' => ['are-you-sleeping', 'row-row-row', 'three-blind-mice', 'londons-burning'],
    ];

    /** How many songs a band samples (a phone shows the first three). */
    public const PER_BAND = 4;

    public function __construct(private readonly SongCollections $collections, private readonly Carols $carols) {}

    /**
     * The page's bands: the carols first in their season (November to Twelfth Night, Carols::inSeason), as the season's
     * full-width band; then each collection that's a page as a panel, the biggest first (the brief asks for
     * popularity: the next Wednesday review's Search Console pull can set it; until then, size). Each with its samples (the
     * collection page's own cards, so each plays its first line) and how many songs the page has.
     *
     * @return list<array{key: string, path: string, count: int, season: bool, samples: list<array<string, mixed>>}>
     */
    public function bands(): array
    {
        $bands = [];

        $carols = $this->carols->songs();
        // The carol season (November to Twelfth Night, Carols::inSeason), as /songs has always linked the page.
        $season = Carols::inSeason() && $carols !== [];
        if ($season) {
            $bands[] = ['key' => 'christmas', 'path' => Carols::PATH, 'count' => count($carols), 'season' => true, 'samples' => self::pick($carols, self::SAMPLES['christmas'])];
        }

        $panels = [];
        foreach ($this->collections->open() as $open) {
            $slug = $open['slug'];
            // A collection's Christmas songs (a carol that's also a hymn) are the carols band's in season, not its sample.
            $cards = array_values(array_filter($this->collections->songs($slug), fn (array $card): bool => ! ($season && Carols::has($card['slug']))));
            $panels[] = ['key' => $slug, 'path' => $open['path'], 'count' => count($this->collections->members($slug)), 'season' => false, 'samples' => self::pick($cards, self::SAMPLES[$slug] ?? [])];
        }
        usort($panels, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $bands = [...$bands, ...$panels];

        // Out of season the carols aren't a band (their songs are in the A to Z); /songs points to the page only in season.

        return $bands;
    }

    /**
     * Every open song, A to Z, each to its own page: nothing more than one click from /songs.
     *
     * @param  Collection<int, Template>  $songs
     * @return list<array{title: string, url: string}>
     */
    public static function index(Collection $songs): array
    {
        return array_values($songs
            ->map(static fn (Template $t): array => ['title' => (string) $t->title, 'url' => '/songs/'.$t->slug])
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->all());
    }

    /**
     * The picks that are open, in their order, then the rest of the collection's cards, up to PER_BAND.
     *
     * @param  list<array<string, mixed>>  $cards
     * @param  list<string>  $picks
     * @return list<array<string, mixed>>
     */
    private static function pick(array $cards, array $picks): array
    {
        $bySlug = array_column($cards, null, 'slug');
        $chosen = array_values(array_filter(array_map(static fn (string $slug): ?array => $bySlug[$slug] ?? null, $picks)));
        foreach ($cards as $card) {
            if (count($chosen) >= self::PER_BAND) {
                break;
            }
            if (! in_array($card['slug'], array_column($chosen, 'slug'), true)) {
                $chosen[] = $card;
            }
        }

        return array_slice($chosen, 0, self::PER_BAND);
    }
}
