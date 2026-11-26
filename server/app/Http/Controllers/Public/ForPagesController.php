<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Languages\Languages;
use App\Support\Pages\Landing;
use App\Support\Pages\Prices;
use App\Support\Site\PathPages;
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
    /**
     * Who it's for (/for; Hillel, 2026-11-24: "add a new whos it for page under products which covers all 3 for pages"):
     * the home's three ways in, each a card in its part's colour with a real picture and a link to its page, then the
     * other audiences the site has a page for, and the first lesson. The footer's one way to them.
     */
    public function index(Request $request): Response
    {
        $title = SitePages::title((string) __('site.for.index.title'));

        return response()->view('site.for-index', [
            'title' => $title,
            'meta' => ['title' => $title, 'description' => (string) __('site.for.index.description'), 'url' => Hosts::publicUrl().'/for'],
            'area' => 'for',
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            // The three paths in the home's order and colours (learn, make, lead), each to its page.
            'paths' => [
                ['key' => 'learn', 'card' => 'learners', 'n' => 1, 'name' => (string) __('pages.nav.learners'), 'url' => '/for/learners', 'shot' => PathPages::shot('learners')],
                ['key' => 'make', 'card' => 'singers', 'n' => 3, 'name' => (string) __('pages.nav.singers'), 'url' => '/for/singers', 'shot' => PathPages::shot('singers')],
                ['key' => 'lead', 'card' => 'choir-directors', 'n' => 4, 'name' => (string) __('pages.nav.for_choirs'), 'url' => '/for/choir-directors', 'shot' => null],
            ],
        ], 200, SongPagesController::headers());
    }

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

        // The home's other two paths (Hillel, 2026-11-23): learners and singers, each the site's own content for that way in.
        if (in_array($who, PathPages::PATHS, true)) {
            return $this->path($request, $who, $page);
        }

        // For choir directors has its own short page (the consultant's concept, 2026-11-23); the others share site.for.
        return response()->view($who === 'choir-directors' ? 'site.for-directors' : 'site.for', [
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

    /**
     * /for/learners and /for/singers: one way in (lesson 1 with no account; the studio on the home's song), then the
     * songs, courses, tools, guides and questions that fit, each the site's own card. A visitor's own ref stays theirs.
     *
     * @param  array<string, mixed>  $page
     */
    private function path(Request $request, string $who, array $page): Response
    {
        $ref = Landing::ref($request->query('ref')) ?? 'for-'.$who;
        $links = Landing::links($ref);
        $price = Prices::of(Prices::currency($request));

        return response()->view('site.for-path', [
            'title' => SitePages::title((string) $page['title']),
            'meta' => ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/for/'.$who],
            'area' => 'for',
            'page' => $page,
            'links' => $links,
            // The first action: lesson 1 (no account) for learners; for singers, the home's song in the app, to record a part.
            'first' => $who === 'learners' ? $links['start'] : Hosts::app('/t/'.Landing::HERO_SONG.'?'.http_build_query(['ref' => $ref])),
            'content' => PathPages::content($who, $price),
        ], 200, [...SongPagesController::headers(), 'Vary' => 'CF-IPCountry, Accept-Language']);
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
