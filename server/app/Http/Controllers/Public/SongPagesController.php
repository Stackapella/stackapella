<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TemplateAccess;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Languages\Dates;
use App\Support\Languages\SongWords;
use App\Support\Pages\Carols;
use App\Support\Pages\Landing;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongMeanings;
use App\Support\Pages\SongPage;
use App\Support\Pages\SongParts;
use App\Support\Pages\SongScore;
use App\Support\Pages\SongsHub;
use App\Support\Pages\SongStories;
use App\Support\Rights\RightsHere;
use App\Support\Sharing\SongCards;
use App\Support\Site\Correction;
use App\Support\SongAudio\SongAudioFiles;
use App\Support\Templates\Catalogue;
use App\Support\Templates\SongVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /songs and /songs/<slug>: every official song's public page (contract/api.md, "Songs, guides
 * and legal pages (L)"). No session, cached 5 minutes, indexed from launch (SITE_INDEXING). The
 * frontend writes the markup (resources/views/public/), this the data.
 */
final class SongPagesController extends Controller
{
    // Five minutes, then the browser shows its copy while it fetches the next (stale-while-revalidate): a returning
    // visitor never waits on the server. Each browser keeps its own copy, so a page that varies by country may share these.
    private const HEADERS = ['Cache-Control' => 'public, max-age=300, stale-while-revalidate=86400'];

    // What Cloudflare's edge may keep, given only to pages that read the same for everyone (self::edgeHeaders): a Cache Rule
    // that respects it makes those pages answer from the edge instead of rendering for every visit. Its stale window is
    // short, so a page that starts to vary (a song withheld somewhere) is served stale at most once more.
    private const EDGE = 'max-age=300, stale-while-revalidate=600';

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [...self::HEADERS, ...(config('ops.indexing') === true ? [] : ['X-Robots-Tag' => 'noindex'])];
    }

    /**
     * A song's page (or part page) headers: a song protected anywhere reads differently by country (docs/geo-rights-plan.md),
     * so no shared cache may keep it.
     *
     * @return array<string, string>
     */
    public static function headersFor(?Template $template, bool $versions = false): array
    {
        // A song with versions (SongVersions) opens on the one the visitor's country sings: it differs by country too.
        return $template !== null && ($versions || app(RightsHere::class)->variesByCountry($template))
            ? [...self::headers(), 'Cache-Control' => 'private, no-store']
            : self::edgeHeaders();
    }

    /**
     * A page that reads the same for every visitor (no country, no cookie, no language but its path's): the song pages,
     * their part pages, /songs, the collections and the carols. Cloudflare's edge may keep it (self::EDGE); no other page
     * says so, so a Cache Rule that respects the header caches only these.
     *
     * @return array<string, string>
     */
    public static function edgeHeaders(): array
    {
        return [...self::headers(), 'Cloudflare-CDN-Cache-Control' => self::EDGE];
    }

    public function index(Request $request, Catalogue $catalogue): Response
    {
        // Only the songs the site may show: one waiting for its Monday stays off (Catalogue::open()), from their index,
        // never every song's notes (the page took 2.2 s in production building them all).
        $songs = $catalogue->openIndex();

        // A sampler (Hillel, 2026-11-11): each collection's band in its page's look with a few songs to play and the way to
        // all of them, then every song A to Z (SongsHub).

        $hub = app(SongsHub::class);
        // The hero's search without the script (Hillel's songbook design): ?q= narrows the A to Z by title; the script
        // does the same as you type, and the page's address stays /songs for search.
        $q = trim((string) $request->query('q', ''));

        return response()->view('public.songs', [
            'count' => count($songs),
            'bands' => $hub->bands(),
            // "Coming up" (Festivals): each festival card its script may pick, and the server's pick for today.
            'next' => $hub->next(),
            'index' => $hub->index(),
            'q' => mb_substr($q, 0, 60),
            // The hero's "Try it": a song as its page's score card shows it, on staves (Hillel, 2026-11-18: "at the top of the
            // songs page we show bars, let's show music staff notes by default instead"; public/_song-score).
            'try' => self::tryIt(),
            'rounds' => in_array('rounds', array_column(app(SongCollections::class)->open(), 'slug'), true) ? SongCollections::path('rounds') : null,
            // The season band's chip: when it ends (Carols::seasonEnds).
            'until' => Dates::dayMonth(Carols::seasonEnds()),
            'meta' => ['title' => __('pages.songs.title'), 'description' => __('pages.songs.description'), 'url' => Hosts::publicUrl().'/songs'],
        ], 200, self::edgeHeaders());
    }

    /**
     * /songs's song to try (Silent Night, else Amazing Grace), as a song page's score card shows it (public/_song-score):
     * its player's song, which plays the whole of it, the score's facts and every note for the engraver, its text
     * alternative, its page and its collection's tone. Null when neither song is there.
     *
     * @return array{line: array<string, mixed>, score: array<string, mixed>, alt: string, url: string, title: string, tone: string}|null
     */
    private static function tryIt(): ?array
    {
        foreach (['silent-night', 'amazing-grace'] as $slug) {
            $template = Template::query()->official()->with('currentVersion')->where('slug', $slug)->first();
            $line = $template !== null ? Landing::line($template) : null;
            if ($template === null || $line === null) {
                continue;
            }
            $song = SongPage::of($template);
            $score = SongScore::of($template, $song, (float) $line['songBeats'] * 60 / max(1.0, (float) $line['tempo']));

            return [
                'line' => $line,
                'score' => $score,
                'alt' => SongScore::alt($song['title'], $score, SongPage::cards($template, $song), count($line['parts'])),
                'url' => '/songs/'.$slug,
                'title' => (string) $song['title'],
                'tone' => app(SongCollections::class)->home($slug)['key'] ?? 'song',
            ];
        }

        return null;
    }

    public function show(Request $request, string $slug, SongCards $cards, Catalogue $catalogue, SongCollections $collections, SongParts $songParts, RightsHere $rights, SongVersions $songVersions): Response|RedirectResponse
    {
        $template = Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->where('slug', $slug)->first();

        // Another version of a song (a UK carol's, SongVersions) has no page of its own: its song's page, showing it.
        if ($template !== null && ($link = SongVersions::of($template)) !== null) {
            return SongVersions::redirect($request, $slug, $link);
        }

        if ($template === null || ! Catalogue::isOpen($template)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/songs']], 404, self::headers());
        }

        // The version shown (Hillel, 2026-11-18, "One song, US/UK switch"): the one asked for (?version=), else the one the
        // visitor's country sings (the UK's, in the UK), else the song's own. `$sung` is the song as the page sings it: its
        // notes, makers and rights; the page, its story and its address stay the song's.
        $country = RightsHere::country($request);
        $versions = array_values(array_filter($songVersions->templates($template), static fn (array $version): bool => Catalogue::isOpen($version['template'])));
        $chosen = count($versions) > 1 ? $songVersions->choose($versions, SongVersions::asked($request->query(SongVersions::PARAM)), $country) : $versions[0];
        $sung = $chosen['template'];
        $song = SongPage::of($sung, $template, count($versions) > 1 ? $chosen['name'] : null);
        $song['versions'] = SongVersions::forPage($versions, $chosen['name'], (string) $song['pageUrl']);
        // Where its copyright still runs (docs/geo-rights-plan.md): the page stays, saying when it's free there; the
        // player goes, and the words too where they're the protected part.
        $withheld = $rights->withheld($sung, $country);
        $wordsWithheld = $rights->wordsWithheld($sung, $country);
        // The whole song, every part mixed (the showreel agent's render of this version): for the page's player and its
        // MusicRecording. Every open song's (Hillel, 2026-11-13: "it should be easy to hear the song on the song page"),
        // not only the bundled ones; never where the music is withheld here.
        $song['audio'] = $withheld !== null ? null : SongAudioFiles::forPage($sung);
        // Free, but a song the app doesn't bundle starts with a free account (2026-11-07): the hero's chip says so, never a
        // bare "Free" that a guest finds asks for an account (frontend2's newcomer walk, 2026-11-13).
        $song['account'] = $sung->access === TemplateAccess::Plus;
        // The hero's player: the opening line's notes (js/public/mixer.js fetches the rest, as a guest may: the
        // catalogue's open grant under its daily cap), and the render, when there is one, for all its parts at once.
        $line = $withheld !== null ? null : Landing::line($sung);
        if ($line !== null) {
            $line['audio'] = $song['audio'] === null ? null : ['m4a' => $song['audio']['m4a'], 'mp3' => $song['audio']['mp3']];
        }
        if ($wordsWithheld) {
            $song['words'] = [];
            $song['lines'] = null;
        }
        // More to learn: the same level first, three of them (picked by the level SongPage::of reads, before building
        // the three: building every song's page for three was most of the page's time).
        // From the open songs' index (Catalogue::openIndex), then only those three read whole: never every song's notes.
        $index = $catalogue->openIndex();
        $picked = collect($index)->reject(static fn (array $other): bool => $other['slug'] === $template->slug)
            ->sortBy(static fn (array $other): int => $other['difficulty'] === $song['difficulty'] ? 0 : 1)
            ->take(3)->pluck('slug')->values()->all();
        $bySlug = Template::query()->listed()->with('currentVersion')->whereIn('slug', $picked)->get()->keyBy('slug');
        $more = array_values(array_filter(array_map(static fn (string $slug): ?array => ($other = $bySlug->get($slug)) instanceof Template ? SongPage::of($other) : null, $picked)));

        // Its story, when the courses lead's is reviewed (SongStories::story, in the page's language while its translation
        // is current): the page then carries its title and description, as its searches were written for.
        $story = SongStories::story((string) $template->slug);
        // Where the words are the protected part here (geo rights), the story keeps its history but not its full words.
        if ($story !== null && $wordsWithheld) {
            $story = SongStories::withoutWords($story);
        }
        // Its Sing next picks only where those songs are open (a story may name one that isn't yet): never a broken link.
        if ($story !== null && is_array($story['next'] ?? null)) {
            $open = array_column($index, 'slug');
            $story['next'] = array_values(array_filter($story['next'], static fn (mixed $next): bool => is_array($next) && in_array($next['slug'] ?? null, $open, true)));
        }
        // A full-words line without a meaning of its own takes the song's meaning for it, where it sings the same.
        if ($story !== null && is_array($story['fullWords'] ?? null)) {
            /** @var list<list<array{sung: string, meaning?: string}>>|null $meanings SongPage::of()'s lines (SongMeanings::forPage) */
            $meanings = is_array($song['lines'] ?? null) ? $song['lines'] : null;
            $story = SongMeanings::fillFullWords($story, $meanings);
        }
        $home = $collections->home((string) $template->slug);
        $partCards = SongPage::cards($sung, $song);

        Correction::share('song', SongWords::title((string) $template->title, (string) $template->slug));

        return response()->view('public.song', [
            'song' => $song,
            'story' => $story,
            // Every verse, one sung line a line; the "Find your part" cards; each part's Learn in the app.
            // Every verse a stanza of sung lines: with each line's script and meaning in the page's language once the song has
            // them ($song['lines'], see public/_song-words), else as sung.
            // The story's full words where it has them (its "### The full words" block, SongStories::fullWords: every stanza
            // with its label, each line's script and meaning), else the template's verses. Null when withheld here.
            // Where the words are the protected part here, none: not the template's verses either.
            'lines' => $wordsWithheld ? [] : (is_array($story['fullWords'] ?? null) ? array_column($story['fullWords']['stanzas'], 'lines') : SongPage::lines($song['lines'] ?? null, SongPage::verses($sung))),
            'labels' => is_array($story['fullWords'] ?? null) ? array_column($story['fullWords']['stanzas'], 'label') : [],
            'wordsIntro' => is_array($story['fullWords'] ?? null) ? (string) $story['fullWords']['intro'] : '',
            'sungLanguage' => is_string($story['sungLanguage'] ?? null) ? $story['sungLanguage'] : (is_string($song['sungLanguage'] ?? null) ? $song['sungLanguage'] : null),
            'cards' => $partCards,
            // Each part's guide voice: a real singer's (credited) or the app's own, one look-up for the song (SongParts::guides).
            'guides' => $songParts->guides($sung),
            // The ask to sing a part with no real voice yet, once the lend_voice flag is on (docs/open-guide-voices-plan.md);
            // a flag this install doesn't have yet is off.
            'lendVoice' => SongParts::lendVoice(),
            'learn' => array_combine(array_column($partCards, 'key'), array_map(static fn (array $card): string => Hosts::app('/?'.http_build_query(['song' => $sung->slug, 'part' => $card['key'], 'ref' => 'song-page'])), $partCards)),
            // The page that collects it gives the hero its palette (its link card's and band's); a song no page collects, the songs' own.
            'tone' => $home['key'] ?? 'song',
            // The source a story's quote is from, by its number.
            'quoteN' => $story !== null && is_array($story['quote'] ?? null) ? (collect((array) $story['sources'])->firstWhere('id', $story['quote']['source'])['n'] ?? null) : null,
            // Every open song plays, not only the bundled ones (Hillel, 2026-11-13); none where it's withheld here.
            'line' => $line,
            // Its score's facts and downloads (SongScore), with the player: never where it's withheld here.
            'score' => $line !== null ? SongScore::of($sung, $song, (float) $line['songBeats'] * 60 / max(1.0, (float) $line['tempo'])) : null,
            // Null where it's free; else the year it's free here (null when that can't be known) and whether the words
            // are withheld too. The view's words are marketing's.
            'rights' => $withheld === null ? null : ['freeFrom' => $withheld['freeFrom'], 'words' => $wordsWithheld],
            'more' => $more,
            // The page that collects it, in its trail and below its more songs (SongCollections::home).
            'home' => $home,
            'meta' => [
                // A story's own title and description where it shows; else a round says round (its searches do), never SATB
                // or harmony; SATB named where it's true (docs/search-content-plan.md, item 4): choirs search it.
                'title' => $story !== null && $story['title'] !== '' ? $story['title'] : (SongParts::round($song['genre'])
                    ? __('pages.song.title_round', ['title' => SongParts::searchTitle((string) $template->title)])
                    : __('pages.song.title', ['title' => SongWords::title((string) $template->title, (string) $template->slug)])),
                'description' => $story !== null && $story['description'] !== '' ? $story['description'] : match (true) {
                    SongParts::round($song['genre']) => __('pages.song.description_round', ['title' => SongParts::searchTitle((string) $template->title)]),
                    SongParts::satb(array_column($song['parts'], 'key')) => __('pages.song.description_satb', ['title' => SongWords::title((string) $template->title, (string) $template->slug)]),
                    default => __('pages.song.description', ['title' => SongWords::title((string) $template->title, (string) $template->slug), 'parts' => implode(', ', array_column($song['parts'], 'name'))]),
                },
                'url' => $song['pageUrl'],
                // The song's own card (made on the first visit; the landing's until then).
                'image' => $cards->url($template, 'join'),
                'imageAlt' => __('site.meta.app.join.image_alt', ['song' => SongWords::title((string) $template->title, (string) $template->slug)]),
            ],
        ], 200, self::headersFor($template, count($versions) > 1));
    }
}
