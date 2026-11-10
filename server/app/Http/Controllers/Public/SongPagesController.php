<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TemplateAccess;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\Carols;
use App\Support\Pages\Landing;
use App\Support\Pages\SongPage;
use App\Support\Pages\SongParts;
use App\Support\Sharing\SongCards;
use App\Support\Templates\Catalogue;
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

    public function index(Catalogue $catalogue): Response
    {
        // Only the songs the site may show: one waiting for its Monday stays off (Catalogue::open()).
        $songs = $catalogue->open();

        return response()->view('public.songs', [
            'songs' => $songs->map(fn (Template $t): array => SongPage::of($t))->values()->all(),
            // Each card's Play: a bundled song's opening line, by slug; any other song's notes come
            // only through the API, to an account (Hillel, 2026-10-27), so it has none.
            'lines' => $songs->mapWithKeys(fn (Template $t): array => [$t->slug => $t->access === TemplateAccess::Plus ? null : Landing::line($t)])->all(),
            'meta' => ['title' => __('pages.songs.title'), 'description' => __('pages.songs.description'), 'url' => Hosts::publicUrl().'/songs'],
            // In the carol season, the Christmas carols page, while a carol is open.
            'carols' => Carols::inSeason() && app(Carols::class)->songs() !== [] ? Carols::PATH : null,
        ], 200, self::headers());
    }

    public function show(string $slug, SongCards $cards, Catalogue $catalogue): Response
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
            'meta' => [
                'title' => __('pages.song.title', ['title' => $template->title]),
                // SATB named where it's true (docs/search-content-plan.md, item 4): choirs search it.
                'description' => SongParts::satb(array_column($song['parts'], 'key'))
                    ? __('pages.song.description_satb', ['title' => $template->title])
                    : __('pages.song.description', ['title' => $template->title, 'parts' => implode(', ', array_column($song['parts'], 'name'))]),
                'url' => $song['pageUrl'],
                // The song's own card (made on the first visit; the landing's until then).
                'image' => $cards->url($template, 'join'),
                'imageAlt' => __('site.meta.app.join.image_alt', ['song' => $template->title]),
            ],
        ], 200, self::headers());
    }
}
