<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Site\OpenSource;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * What Stackapella does (docs/public-site-plan.md): /features, a card per feature, and a page for
 * each, open or coming soon. No session; cached five minutes like the song pages.
 */
final class FeaturePagesController extends Controller
{
    public function index(Request $request): Response
    {
        return response()->view('site.features', [
            'title' => SitePages::title(__('site.features.index.title')),
            'meta' => ['title' => SitePages::title(__('site.features.index.title')), 'description' => __('site.features.index.description'), 'url' => Hosts::publicUrl().'/features'],
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'cards' => $cards = SitePages::cards(SitePages::FEATURES),
            'works' => array_values(array_filter($cards, static fn (array $card): bool => ! $card['soon'])),
            'coming' => array_values(array_filter($cards, static fn (array $card): bool => $card['soon'])),
            'who' => SitePages::who(),
            'draw' => SitePages::draw(),
            'openSource' => OpenSource::on(),
            'openPoints' => array_values((array) Lang::get('site.features_open.points')),
        ], 200, SongPagesController::headers());
    }

    public function show(Request $request, string $slug): Response
    {
        if (! in_array($slug, SitePages::FEATURES, true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/features']], 404, SongPagesController::headers());
        }

        $page = SitePages::page('features', $slug);

        return response()->view('site.page', [
            'title' => SitePages::title((string) $page['title']),
            'meta' => ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/features/'.$slug],
            'area' => 'features',
            'page' => $page,
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'hero' => in_array($slug, SitePages::WITH_MIXER, true) ? Landing::hero() : null,
            'cards' => SitePages::related($slug, shown: SitePages::picturesOf($page)),
            'draw' => SitePages::draw(),
        ], 200, SongPagesController::headers());
    }
}
