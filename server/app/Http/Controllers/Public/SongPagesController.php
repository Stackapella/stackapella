<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TemplateAccess;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\Carols;
use App\Support\Pages\Landing;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongPage;
use App\Support\Pages\SongParts;
use App\Support\Pages\SongsHub;
use App\Support\Sharing\SongCards;
use App\Support\Templates\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /songs and /songs/<slug>: every official song's public page (contract/api.md, "Songs, guides
 * and legal pages (L)"). No session, cached 5 minutes, indexed from launch (SITE_INDEXING). The
 * frontend writes the markup (resources/views/public/), this the data.
 */
final class SongPagesController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'public, max-age=300'];

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [...self::HEADERS, ...(config('ops.indexing') === true ? [] : ['X-Robots-Tag' => 'noindex'])];
    }

    public function index(Request $request, Catalogue $catalogue): Response
    {
        // Only the songs the site may show: one waiting for its Monday stays off (Catalogue::open()).
        $songs = $catalogue->open();

        // A sampler (Hillel, 2026-11-11): each collection's band in its page's look with a few songs to play and the way to
        // all of them, then every song A to Z (SongsHub).

        $hub = app(SongsHub::class);
        // The hero's search without the script (Hillel's songbook design): ?q= narrows the A to Z by title; the script
        // does the same as you type, and the page's address stays /songs for search.
        $q = trim((string) $request->query('q', ''));

        return response()->view('public.songs', [
            'count' => $songs->count(),
            'bands' => $hub->bands(),
            'index' => $hub->index($songs),
            'q' => mb_substr($q, 0, 60),
            // The hero's "Try it": a song's first line in four parts, each switchable (x-site.mixer, js/public/mixer.js).
            'try' => Landing::lineOf('silent-night') ?? Landing::lineOf('amazing-grace'),
            'rounds' => in_array('rounds', array_column(app(SongCollections::class)->open(), 'slug'), true) ? SongCollections::path('rounds') : null,
            // The season band's chip: when it ends (Carols::seasonEnds).
            'until' => Carols::seasonEnds()->translatedFormat('j F'),
            'meta' => ['title' => __('pages.songs.title'), 'description' => __('pages.songs.description'), 'url' => Hosts::publicUrl().'/songs'],
        ], 200, self::headers());
    }

    public function show(string $slug, SongCards $cards, Catalogue $catalogue, SongCollections $collections): Response
    {
        $template = Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->where('slug', $slug)->first();

        if ($template === null || ! Catalogue::isOpen($template)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/songs']], 404, self::headers());
        }

        $song = SongPage::of($template);
        // More to learn: the same level first, three of them.
        $more = $catalogue->open()->reject(fn (Template $other): bool => $other->is($template))
            ->map(fn (Template $other): array => SongPage::of($other))
            ->sortBy(fn (array $other): int => $other['difficulty'] === $song['difficulty'] ? 0 : 1)
            ->take(3)->values()->all();

        return response()->view('public.song', [
            'song' => $song,
            // A bundled song plays its opening line; any other only its clip: its notes come only to
            // an account, through the API (Hillel, 2026-10-27).
            'line' => $template->access === TemplateAccess::Plus ? null : Landing::line($template),
            'more' => $more,
            // The page that collects it, in its trail and below its more songs (SongCollections::home).
            'home' => $collections->home((string) $template->slug),
            'meta' => [
                // A round says round (its searches do), never SATB or harmony; SATB named where it's true
                // (docs/search-content-plan.md, item 4): choirs search it.
                'title' => SongParts::round($song['genre'])
                    ? __('pages.song.title_round', ['title' => SongParts::searchTitle((string) $template->title)])
                    : __('pages.song.title', ['title' => $template->title]),
                'description' => match (true) {
                    SongParts::round($song['genre']) => __('pages.song.description_round', ['title' => SongParts::searchTitle((string) $template->title)]),
                    SongParts::satb(array_column($song['parts'], 'key')) => __('pages.song.description_satb', ['title' => $template->title]),
                    default => __('pages.song.description', ['title' => $template->title, 'parts' => implode(', ', array_column($song['parts'], 'name'))]),
                },
                'url' => $song['pageUrl'],
                // The song's own card (made on the first visit; the landing's until then).
                'image' => $cards->url($template, 'join'),
                'imageAlt' => __('site.meta.app.join.image_alt', ['song' => $template->title]),
            ],
        ], 200, self::headers());
    }
}
