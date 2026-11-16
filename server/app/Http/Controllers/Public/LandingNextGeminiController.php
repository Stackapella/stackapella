<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\LandingNextGemini;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * The landing's next version, the gemini variant (docs/landing-next-gemini-plan.md): a candidate Hillel compares
 * beside the others, at its own address in English and Spanish. Never indexed (the header and the page both say so),
 * with no canonical and no alternates, out of the sitemap, linked from nowhere, and counted only under its own kind.
 */
final class LandingNextGeminiController extends Controller
{
    public function __invoke(Request $request, string $language = 'en'): Response
    {
        // The page's language for this request only (as SiteLanguage does for the listed pages): a long-running
        // worker serves the next one as it was.
        $before = App::getLocale();
        App::setLocale($language);

        try {
            return response()->view('pages.landing-next-gemini', [
                'language' => $language,
                'stage' => LandingNextGemini::stage(),
                'links' => LandingNextGemini::links($language),
                'counts' => LandingNextGemini::counts(),
                // Its one anonymous count, only where the site's own are sent (counts on, the public site).
                'visit' => config('counts.enabled') === true && (! Hosts::split() || Hosts::onPublicHost($request)) ? LandingNextGemini::VISIT : null,
            ], 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-cache']);
        } finally {
            App::setLocale($before);
        }
    }
}
