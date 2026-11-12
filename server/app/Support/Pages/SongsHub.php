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
        'jewish-songs' => ['maoz-tzur', 'oy-chanukah', 'adir-hu', 'ein-keloheinu'],
        'folk-songs' => ['danny-boy', 'auld-lang-syne', 'the-water-is-wide', 'sakura-sakura'],
        'barbershop-songs' => ['sweet-adeline', 'daisy-bell', 'let-me-call-you-sweetheart', 'asleep-in-the-deep'],
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
            $bands[] = ['key' => 'christmas', 'path' => Carols::PATH, 'count' => count($carols), 'season' => true, 'samples' => self::pick($carols, self::SAMPLES['christmas'], 'christmas')];
        }

        $panels = [];
        foreach ($this->collections->open() as $open) {
            $slug = $open['slug'];
            // A collection's Christmas songs (a carol that's also a hymn) are the carols band's in season, not its sample.
            $cards = array_values(array_filter($this->collections->songs($slug), fn (array $card): bool => ! ($season && Carols::has($card['slug']))));
            $panels[] = ['key' => $slug, 'path' => $open['path'], 'count' => count($this->collections->members($slug)), 'season' => false, 'samples' => self::pick($cards, self::SAMPLES[$slug] ?? [], $slug)];
        }
        // The biggest first; the Jewish songs first of all around Hanukkah and Passover (JewishSongs::inSeason), right after
        // the carols' band in December.
        $first = JewishSongs::inSeason() ? JewishSongs::SLUG : null;
        usort($panels, static fn (array $a, array $b): int => [$b['key'] === $first, $b['count']] <=> [$a['key'] === $first, $a['count']]);
        $bands = [...$bands, ...$panels];

        // Out of season the carols aren't a band (their songs are in the A to Z); /songs points to the page only in season.

        return $bands;
    }

    /**
     * Every open song, A to Z, each to its own page, so nothing is more than one click from /songs; each with the
     * collections that list it (the A to Z's filters, Hillel's songbook design) and its dot, the first of them in
     * /songs's group order, or `other` (folk and parlour) when no collection lists it.
     *
     * @param  Collection<int, Template>  $songs
     * @return list<array{title: string, url: string, letter: string, in: list<string>, dot: string}>
     */
    public function index(Collection $songs): array
    {
        $carols = array_column($this->carols->songs(), 'slug');
        $members = ['christmas' => $carols];
        foreach ($this->collections->open() as $open) {
            $members[$open['slug']] = $this->collections->members($open['slug']);
        }

        return array_values($songs
            ->map(static function (Template $t) use ($members): array {
                $in = array_keys(array_filter($members, static fn (array $slugs): bool => in_array((string) $t->slug, $slugs, true)));
                $order = array_keys(SongCollections::GROUPS);
                usort($in, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));
                $title = (string) $t->title;

                return ['title' => $title, 'url' => '/songs/'.$t->slug, 'letter' => mb_strtoupper(mb_substr(ltrim($title, '"\'('), 0, 1)), 'in' => $in === [] ? ['other'] : $in, 'dot' => $in[0] ?? 'other'];
            })
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
    private static function pick(array $cards, array $picks, string $key = ''): array
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

        // Each with its kind for its card's line (Hillel's songbook design: "C major · SATB"): a round, four choir parts, or how many.
        return array_map(static fn (array $card): array => [...$card, 'kind' => $key === 'rounds' ? 'round' : (array_column($card['parts'], 'key') === SongParts::SATB ? 'satb' : 'parts')], array_slice($chosen, 0, self::PER_BAND));
    }
}
