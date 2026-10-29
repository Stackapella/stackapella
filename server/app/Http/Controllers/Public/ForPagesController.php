<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Who Stackapella is for (docs/public-site-plan.md): /for/<who>, each with the features that
 * matter to them. No session; cached five minutes like the song pages.
 */
final class ForPagesController extends Controller
{
    public function show(Request $request, string $who): Response
    {
        if (! in_array($who, SitePages::FOR, true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/features']], 404, SongPagesController::headers());
        }

        $page = SitePages::page('for', $who);

        return response()->view('site.for', [
            'title' => SitePages::title((string) $page['title']),
            'meta' => ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/for/'.$who],
            'area' => 'for',
            'page' => $page,
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'hero' => null,
            'cards' => SitePages::unshown(SitePages::cards(array_values(array_filter((array) ($page['features'] ?? []), 'is_string'))), SitePages::picturesOf($page)),
            'draw' => SitePages::draw(),
        ], 200, SongPagesController::headers());
    }
}
