<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Pages\PageGone;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongParts;
use App\Support\Sharing\SongCards;
use App\Support\Site\Correction;
use Illuminate\Http\Response;

/**
 * A song's part page (docs/seo-growth-plan.md, item 1): /songs/<song>/<part>, only for a live,
 * reviewed, public-domain library song (App\Support\Pages\SongParts). Anything else, and every part
 * page until its view exists, is a real 404. No session; cached five minutes like the song pages.
 */
final class SongPartPagesController extends Controller
{
    public function show(string $slug, string $part, SongParts $parts, SongCollections $collections, SongCards $cards): Response
    {
        $page = SongParts::open() ? $parts->page($slug, $part) : null;

        if ($page === null) {
            return PageGone::response('/songs/'.$slug);
        }

        // The page that collects the song, in the trail and below the other parts (SongCollections::home).
        $page['home'] = $collections->home($slug);

        // Its link card is its song's (the song page's own, SongCards), not the landing's (the coordinator's audit, 2026-11-11).
        $template = Template::query()->with('currentVersion')->where('slug', $slug)->first();

        Correction::share('part', trim((string) ($page['song']['title'] ?? '').' · '.(string) (is_array($page['part'] ?? null) ? ($page['part']['name'] ?? '') : ''), ' ·'));

        return response()->view(SongParts::VIEW, ['songPart' => $page, 'image' => $template !== null ? $cards->url($template, 'join') : SongCards::fallback()], 200, SongPagesController::headersFor($template));
    }
}
