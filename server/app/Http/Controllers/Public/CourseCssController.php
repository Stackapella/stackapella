<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Site\CourseCss;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page's course stylesheet (docs/css-split-plan.md, step 1; App\Support\Site\CourseCss): /course-css/<prefixes>.css,
 * the prefixes sorted and each once, every one a course with rules of its own (anything else is a 404, and another order
 * of the same ones a 301 to theirs, so no one can make up cache keys). Built once per courses.css and set of courses, in
 * the cache. Kept a year in the browser and at the edge only at this build's ?v= (courses.css's hash), as nginx keeps the
 * static files (App\Support\Http\StaticVersions): any other v revalidates, so an old container in a rolling deploy never
 * leaves a year of the wrong sheet.
 */
final class CourseCssController extends Controller
{
    private const MAX = 12;

    public function __invoke(Request $request, string $key): Response|RedirectResponse
    {
        $web = (string) config('app.web_path');
        $version = CourseCss::version($web);
        $prefixes = $key === CourseCss::BASE ? [] : explode('.', $key);

        if ($version === '' || count($prefixes) > self::MAX || array_diff($prefixes, CourseCss::known($web)) !== []) {
            abort(404);
        }

        $canonical = CourseCss::key($prefixes);

        if ($canonical !== $key) {
            $query = $request->getQueryString();

            return redirect('/course-css/'.$canonical.'.css'.($query !== null && $query !== '' ? '?'.$query : ''), 301);
        }

        $css = (string) Cache::rememberForever('course-css:'.$version.':'.$canonical, static fn (): string => CourseCss::sheet($web, $prefixes));
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
