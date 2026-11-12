<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Site\SiteCss;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page family's stylesheets (docs/css-split-plan.md, step 4; App\Support\Site\SiteCss): /site-css/<family>.css, site.css
 * less the rules the family can't use, and /site-css/<family>-game.css, site-game.css the same way. Only the known
 * families (anything else is a 404). Built once per version and family in the cache. Kept a year in the browser and at
 * the edge only at this build's ?v= (SiteCss::version), as nginx keeps the static files (App\Support\Http\StaticVersions):
 * any other v revalidates, so an old container in a rolling deploy never leaves a year of the wrong sheet.
 */
final class SiteCssController extends Controller
{
    public function __invoke(Request $request, string $sheet): Response
    {
        $web = (string) config('app.web_path');
        $version = SiteCss::version($web);
        $game = str_ends_with($sheet, '-game');
        $family = $game ? substr($sheet, 0, -5) : $sheet;

        if ($version === '' || ! array_key_exists($family, SiteCss::FAMILIES)) {
            abort(404);
        }

        $css = (string) Cache::rememberForever('site-css:'.$version.':'.$sheet, static fn (): string => SiteCss::sheet($web, $family, $game));
        $current = $request->query('v') === $version;
        $response = response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => $current ? 'public, max-age=31536000, immutable' : 'no-cache, max-age=0, must-revalidate',
            'Cloudflare-CDN-Cache-Control' => $current ? 'public, max-age=31536000' : 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(substr(hash('xxh128', $css), 0, 16));
        $response->isNotModified($request);

        return $response;
    }
}
