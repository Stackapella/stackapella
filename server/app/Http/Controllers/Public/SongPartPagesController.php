<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Pages\PageGone;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongParts;
use App\Support\Rights\RightsHere;
use App\Support\Sharing\SongCards;
use App\Support\Site\Correction;
use App\Support\Templates\Catalogue;
use App\Support\Templates\SongVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A song's part page (docs/seo-growth-plan.md, item 1): /songs/<song>/<part>, only for a live,
 * reviewed, public-domain library song (App\Support\Pages\SongParts). Anything else, and every part
 * page until its view exists, is a real 404. No session; cached five minutes like the song pages.
 * Where the song's copyright still runs (docs/geo-rights-plan.md) the page stays without its player, as the song's does.
 */
final class SongPartPagesController extends Controller
{
    public function show(Request $request, string $slug, string $part, SongParts $parts, SongCollections $collections, SongCards $cards, RightsHere $rights, SongVersions $songVersions): Response|RedirectResponse
    {
        // Its link card is its song's (the song page's own, SongCards), not the landing's (the coordinator's audit, 2026-11-11).
        $template = Template::query()->listed()->with('currentVersion')->where('slug', $slug)->first();

        // Another version of a song (a UK carol's, SongVersions) has no pages of its own: its song's part page, showing it.
        if ($template !== null && ($link = SongVersions::of($template)) !== null) {
            return SongVersions::redirect($request, $slug, $link);
        }

        // The version sung (Hillel, 2026-11-18, "One song, US/UK switch"), as the song's page picks it: ?version=, else the
        // one the visitor's country sings, else the song's own. Its notes, voices and rights; the page's address the song's.
        $country = RightsHere::country($request);
        $versions = $template === null ? [] : array_values(array_filter($songVersions->templates($template), static fn (array $version): bool => Catalogue::isOpen($version['template'])));
        $chosen = count($versions) > 1 ? $songVersions->choose($versions, SongVersions::asked($request->query(SongVersions::PARAM)), $country) : null;
        $sung = $chosen !== null && $chosen['template'] !== $template ? $chosen['template'] : null;

        $page = SongParts::open() ? $parts->page($slug, $part, $sung, $chosen['name'] ?? null) : null;

        if ($page === null) {
            return PageGone::response('/songs/'.$slug);
        }
        $page['versions'] = $chosen !== null ? SongVersions::forPage($versions, $chosen['name'], (string) $page['canonical']) : [];

        // The page that collects the song, in the trail and below the other parts (SongCollections::home).
        $page['home'] = $collections->home($slug);

        // Withheld where the visitor is: no player (the API would refuse its notes), and the page says why.
        $page['withheld'] = $template !== null && $rights->withheld($sung ?? $template, $country) !== null;
        if ($page['withheld']) {
            $page['song']['free'] = false;
        }

        Correction::share('part', trim((string) ($page['song']['title'] ?? '').' · '.(string) (is_array($page['part'] ?? null) ? ($page['part']['name'] ?? '') : ''), ' ·'));

        return response()->view(SongParts::VIEW, ['songPart' => $page, 'image' => $template !== null ? $cards->url($template, 'join') : SongCards::fallback()], 200, SongPagesController::headersFor($template, $chosen !== null));
    }
}
