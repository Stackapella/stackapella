<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Support\Languages\SongWords;
use App\Support\Templates\Catalogue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
        // The demand check's leads (2026-11-12): what people search first.
        'scottish-songs' => ['auld-lang-syne', 'loch-lomond', 'ye-banks-and-braes', 'red-red-rose'],
        'villancicos' => ['noche-de-paz', 'riu-riu-chiu', 'pastores-a-belen', 'el-cant-dels-ocells'],
        'thanksgiving-hymns' => ['now-thank-we-all-our-god', 'praise-to-the-lord', 'come-ye-thankful-people', 'we-gather-together'],
        'shaker-songs' => ['simple-gifts', 'i-will-bow-and-be-simple', 'o-the-beautiful-treasure', 'who-will-bow-and-bend'],
        'choral-classics' => ['ave-verum-corpus', 'now-is-the-month-of-maying', 'the-lord-bless-you', 'brahms-lullaby'],
    ];

    /** How many songs a band samples (a phone shows the first three). */
    public const PER_BAND = 4;

    /** @var array<string, array<string, mixed>>|null every card read so far (built()) */
    private ?array $built = null;

    /** @var list<string> every slug built() has been asked for */
    private array $asked = [];

    public function __construct(private readonly SongCollections $collections, private readonly Carols $carols, private readonly Catalogue $catalogue) {}

    /**
     * The page's bands: the carols first in their season (November to Twelfth Night, Carols::inSeason), as the season's
     * full-width band; then each collection that's a page as a panel, the biggest first (the brief asks for
     * popularity: the next Wednesday review's Search Console pull can set it; until then, size). Each with its samples (the
     * collection page's own cards, so each plays its first line) and how many songs the page has.
     *
     * @return list<array{key: string, path: string, count: int, season: bool, samples: list<array<string, mixed>>, order?: int}>
     */
    public function bands(): array
    {
        // Kept under the catalogue's fingerprint, this build, the page's language and today (the seasons turn by the day):
        // building each sample's card (its opening line to play) was most of /songs's time.
        $key = 'songs.bands.'.$this->catalogue->fingerprint().'.'.(string) config('app.build').'.'.app()->getLocale().'.'.now()->toDateString();

        /** @var list<array{key: string, path: string, count: int, season: bool, samples: list<array<string, mixed>>}> */
        return Cache::remember($key, now()->addDay(), fn (): array => $this->build());
    }

    /**
     * @return list<array{key: string, path: string, count: int, season: bool, samples: list<array<string, mixed>>}>
     */
    private function build(): array
    {
        // Each band's samples chosen first (the carols by slug, Carols::slugs, and each collection's from the open songs'
        // index), then every sample's card built from one read of just those songs (each plays its first line).
        $carols = $this->carols->slugs();
        // The carol season (November to Twelfth Night, Carols::inSeason), as /songs has always linked the page.
        $season = Carols::inSeason() && $carols !== [];
        $chosen = $season ? ['christmas' => self::choose($carols, self::SAMPLES['christmas'])] : [];
        foreach ($this->collections->open() as $open) {
            $slug = $open['slug'];
            // A collection's Christmas songs (a carol that's also a hymn) are the carols band's in season, not its sample;
            // the villancicos are all Christmas songs, so theirs stay.
            $ordered = array_values(array_filter($this->collections->ordered($slug), static fn (string $song): bool => $slug === 'villancicos' || ! ($season && Carols::has($song))));
            $chosen[$slug] = self::choose($ordered, self::SAMPLES[$slug] ?? []);
        }
        $cards = $this->built(array_merge([], ...array_values($chosen)));
        $samples = static fn (string $key): array => array_values(array_filter(array_map(static fn (string $song): ?array => $cards[$song] ?? null, $chosen[$key])));

        $bands = $season ? [['key' => 'christmas', 'path' => Carols::PATH, 'count' => count($carols), 'season' => true, 'samples' => self::kinds($samples('christmas'), 'christmas')]] : [];
        $panels = [];
        foreach ($this->collections->open() as $open) {
            $slug = $open['slug'];
            $panels[] = ['key' => $slug, 'path' => $open['path'], 'count' => count($this->collections->members($slug)), 'season' => false, 'samples' => self::kinds($samples($slug), $slug)];
        }
        // The biggest first (each keeps that place as its `order`, for the page's script); then the collection whose
        // festival is coming up (Festivals: Hanukkah and Passover's Jewish songs, a Burns Night, a St Patrick's Day) first
        // of all, right after the carols' band in December. On the Spanish site in the carol season, the villancicos lead
        // instead (Hillel's new collections: they lead on /es).
        usort($panels, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        foreach ($panels as $i => $panel) {
            $panels[$i]['order'] = $i;
        }
        $coming = Festivals::open($this->live())[0] ?? null;
        $first = $season && app()->getLocale() === 'es' ? 'villancicos' : ($coming !== null ? self::band($coming['collection']) : null);
        usort($panels, static fn (array $a, array $b): int => [$b['key'] === $first, $b['order'] * -1] <=> [$a['key'] === $first, $a['order'] * -1]);
        $bands = [...$bands, ...$panels];

        // Out of season the carols aren't a band (their songs are in the A to Z); /songs points to the page only in season.

        return $bands;
    }

    /**
     * "Coming up" at the top of /songs (Hillel, 2026-11-12: "have the main songs page automatically highlight which
     * section is coming up next"): a card for each festival that's open or opens soon and leads to a live collection
     * (Festivals::candidates), and which the server shows for its own day: `pick` as the card, `also` as the smaller line
     * under it (indexes into `cards`, or null). js/public/coming-up.js picks again on the visitor's day.
     *
     * @return array{cards: list<array<string, mixed>>, pick: int|null, also: int|null}
     */
    public function next(): array
    {
        $live = $this->live();
        $festivals = Festivals::candidates($live);
        // Each festival's songs chosen first, then every card built from one read of just those songs (as bands()).
        $chosen = array_map(fn (array $f): array => $this->festivalSlugs($f['key'], self::band($f['collection'])), $festivals);
        $built = $this->built(array_merge([], ...$chosen));
        $cards = [];
        $index = [];
        foreach ($festivals as $i => $f) {
            $band = self::band($f['collection']);
            $words = $band === 'christmas' ? 'site.carols' : 'site.collections.'.$band;
            $count = $band === 'christmas' ? count($this->carols->slugs()) : count($this->collections->members($band));
            $songs = array_values(array_filter(array_map(static fn (string $slug): ?array => $built[$slug] ?? null, $chosen[$i])));
            $index[$f['key'].'@'.$f['from']->format('Y-m-d')] = count($cards);
            $cards[] = Festivals::card($f, (string) __('site.festivals.'.$f['key']), [
                'kick' => 'pages.songs.next.kick', 'kick_on' => 'pages.songs.next.kick_on', 'days' => 'pages.songs.next.days', 'tonight' => 'pages.songs.next.tonight',
                'today' => 'pages.songs.next.today', 'start' => 'pages.songs.next.start', 'start_evening' => 'pages.songs.next.start_evening', 'until' => 'pages.songs.next.until',
            ], $songs, (string) __('pages.songs.next.learn'), ['url' => $band === 'christmas' ? Carols::PATH : SongCollections::path($band), 'label' => (string) __($words.'.all', ['count' => $count])]);
        }
        $open = array_map(static fn (array $f): ?int => $index[$f['key'].'@'.$f['from']->format('Y-m-d')] ?? null, Festivals::open($live));

        return ['cards' => $cards, 'pick' => $open[0] ?? null, 'also' => $open[1] ?? null];
    }

    /**
     * The cards for these songs, from one read shared by bands() and next(): the first call reads every song either
     * will show (the bands' samples and the festivals' songs), so /songs reads the song files once.
     *
     * @param  list<string>  $slugs
     * @return array<string, array<string, mixed>>
     */
    private function built(array $slugs): array
    {
        // A slug asked for before is never read again, even when it has no card (a song not open).
        if ($this->built === null || array_diff($slugs, $this->asked) !== []) {
            $festivals = $this->built === null ? array_merge([], ...array_map(fn (array $f): array => $this->festivalSlugs($f['key'], self::band($f['collection'])), Festivals::candidates($this->live()))) : [];
            $this->asked = array_values(array_unique([...$this->asked, ...$slugs, ...$festivals]));
            $this->built = array_column($this->collections->cards($this->asked), null, 'slug');
        }

        return $this->built;
    }

    /**
     * The collections that are live pages, as the calendar names them (the carols' is christmas-carols).
     *
     * @return list<string>
     */
    private function live(): array
    {
        return array_values([...array_map(static fn (array $open): string => $open['slug'], $this->collections->open()), ...($this->carols->slugs() !== [] ? ['christmas-carols'] : [])]);
    }

    /** A calendar collection's band on /songs (the carols' is christmas). */
    private static function band(string $collection): string
    {
        return $collection === 'christmas-carols' ? 'christmas' : $collection;
    }

    /**
     * A festival's songs to learn in time, at most three, by slug: the Jewish songs' tagged with that festival, else its
     * collection's first.
     *
     * @return list<string>
     */
    private function festivalSlugs(string $key, string $band): array
    {
        if ($band === 'christmas') {
            return array_slice($this->carols->slugs(), 0, 3);
        }
        $slugs = $this->collections->ordered($band);
        if ($band === JewishSongs::SLUG) {
            // From the open songs' index (Catalogue::openIndex), never their notes.
            $tagged = array_column(array_filter($this->catalogue->openIndex(), static fn (array $t): bool => JewishSongs::tag($t['genre']) === $key), 'slug');
            $slugs = array_values(array_filter($slugs, static fn (string $slug): bool => in_array($slug, $tagged, true)));
        }

        return array_slice($slugs, 0, 3);
    }

    /**
     * Every open song, A to Z, each to its own page, so nothing is more than one click from /songs; each with the
     * collections that list it (the A to Z's filters, Hillel's songbook design) and its dot, the first of them in
     * /songs's group order, or `other` (folk and parlour) when no collection lists it.
     *
     * From the open songs' index (Catalogue::openIndex), never their notes.
     *
     * @return list<array{title: string, url: string, letter: string, in: list<string>, dot: string}>
     */
    public function index(): array
    {
        $members = ['christmas' => $this->carols->slugs()];
        foreach ($this->collections->open() as $open) {
            $members[$open['slug']] = $this->collections->members($open['slug']);
        }

        return array_values(collect($this->catalogue->openIndex())
            ->map(static function (array $t) use ($members): array {
                $in = array_keys(array_filter($members, static fn (array $slugs): bool => in_array($t['slug'], $slugs, true)));
                $order = array_keys(SongCollections::GROUPS);
                usort($in, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));
                // A round's note in the page's language: "(canon)" in Spanish.
                $title = SongWords::title($t['title'], $t['slug']);

                return ['title' => $title, 'url' => '/songs/'.$t['slug'], 'letter' => mb_strtoupper(mb_substr(ltrim($title, '"\'('), 0, 1)), 'in' => $in === [] ? ['other'] : $in, 'dot' => $in[0] ?? 'other'];
            })
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->all());
    }

    /**
     * The picks that are open, in their order, then the rest of the collection's songs in its order, up to PER_BAND.
     *
     * @param  list<string>  $ordered  the collection's open songs, in its page's order
     * @param  list<string>  $picks
     * @return list<string>
     */
    private static function choose(array $ordered, array $picks): array
    {
        $chosen = array_values(array_intersect($picks, $ordered));

        return array_slice(array_values(array_unique([...$chosen, ...$ordered])), 0, self::PER_BAND);
    }

    /**
     * Each with its kind for its card's line (Hillel's songbook design: "C major · SATB"): a round, four choir parts, or how many.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private static function kinds(array $cards, string $key): array
    {
        return array_map(static fn (array $card): array => [...$card, 'kind' => $key === 'rounds' ? 'round' : (array_column($card['parts'], 'key') === SongParts::SATB ? 'satb' : 'parts')], $cards);
    }
}
