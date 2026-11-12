<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Languages\Languages;
use App\Support\Pages\Landing;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * Who Stackapella is for (docs/public-site-plan.md): /for/<who>, each with the features that
 * matter to them. No session; cached five minutes like the song pages.
 */
final class ForPagesController extends Controller
{
    public function show(Request $request, string $who, Languages $languages): Response
    {
        if (! in_array($who, SitePages::FOR_PAGES, true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/features']], 404, SongPagesController::headers());
        }

        $page = SitePages::page('for', $who);
        // A page's questions, if it has them (choir directors').
        $page['faq'] = array_values(array_filter((array) ($page['faq'] ?? []), 'is_array'));
        // Arrived by one of the For choirs lead-ins (?ref=for-choirs-<place>, Hillel, 2026-11-12: For choirs left the header
        // for the pages a director reads): starting a group is counted by that place too, as the Start links are.
        $ref = Landing::ref($request->query('ref'));
        if ($ref !== null && str_starts_with($ref, 'for-choirs-') && in_array($ref, (array) config('counts.sources'), true) && is_string($page['group_start'] ?? null)) {
            $page['group_start'] = $ref;
        }

        return response()->view('site.for', [
            'title' => SitePages::title((string) $page['title']),
            'meta' => ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/for/'.$who],
            'area' => 'for',
            'page' => $page,
            // A notice's :languages: "English only", or the languages open to everyone ("English and Spanish").
            'languages' => self::languages($languages->offered()),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'hero' => null,
            'cards' => SitePages::unshown(SitePages::cards(array_values(array_filter((array) ($page['features'] ?? []), 'is_string'))), SitePages::picturesOf($page)),
            'draw' => SitePages::draw(),
        ], 200, SongPagesController::headers());
    }

    /** @param  list<string>  $offered */
    private static function languages(array $offered): string
    {
        $words = (array) Lang::get('site.for.languages');
        $names = array_values(array_map(static fn (string $code): string => (string) ($words['names'][$code] ?? $code), $offered));
        if (count($names) < 2) {
            return (string) $words['only'];
        }
        $last = array_pop($names);

        return str_replace([':first', ':last'], [implode(', ', $names), $last], (string) $words['list']);
    }
}
