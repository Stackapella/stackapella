<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TemplateAccess;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\SongPage;
use App\Support\Sharing\SongCards;
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

    public function index(): Response
    {
        $songs = Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->orderBy('title')->get();

        return response()->view('public.songs', [
            'songs' => $songs->map(fn (Template $t): array => SongPage::of($t))->values()->all(),
            // Each card's Play: a free song's opening line, by slug; a Plus song's notes never
            // leave the app (Hillel, 2026-10-27), so it has none.
            'lines' => $songs->mapWithKeys(fn (Template $t): array => [$t->slug => $t->access === TemplateAccess::Plus && ! $t->isFreeOn(now()) ? null : Landing::line($t)])->all(),
            'meta' => ['title' => __('pages.songs.title'), 'description' => __('pages.songs.description'), 'url' => Hosts::publicUrl().'/songs'],
        ], 200, self::headers());
    }

    public function show(string $slug, SongCards $cards): Response
    {
        $template = Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->where('slug', $slug)->first();

        if ($template === null) {
            return response()->view('public.page-gone', ['links' => ['app' => '/songs']], 404, self::headers());
        }

        $song = SongPage::of($template);
        // More to learn: free songs first for a free song, the same level first, three of them.
        $more = Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->whereKeyNot($template->getKey())->orderBy('title')->get()
            ->map(fn (Template $other): array => SongPage::of($other))
            ->sortBy(fn (array $other): array => [$other['plus'] === $song['plus'] ? 0 : 1, $other['difficulty'] === $song['difficulty'] ? 0 : 1])
            ->take(3)->values()->all();

        return response()->view('public.song', [
            'song' => $song,
            // A free song plays its opening line; a Plus song only its clip (Hillel, 2026-10-27).
            'line' => $song['plus'] ? null : Landing::line($template),
            'more' => $more,
            'meta' => [
                'title' => __('pages.song.title', ['title' => $template->title]),
                'description' => __('pages.song.description', ['title' => $template->title, 'parts' => implode(', ', array_column($song['parts'], 'name'))]),
                'url' => $song['pageUrl'],
                // The song's own card (made on the first visit; the landing's until then).
                'image' => $cards->url($template, 'join'),
                'imageAlt' => __('site.meta.app.join.image_alt', ['song' => $template->title]),
            ],
        ], 200, self::headers());
    }
}
