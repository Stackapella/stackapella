<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\Prices;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;

/**
 * How Stackapella compares with other apps (docs/compare-pages-plan.md): /compare, the hub, and a
 * page for each, from site.compare.pages. They answer while the compare_pages switch is off but
 * are kept out of search (X-Robots-Tag) and the sitemap until it's on. Gone until their words and
 * views are there. Cached five minutes like the feature pages.
 */
final class ComparePagesController extends Controller
{
    public function index(Request $request): Response
    {
        $slugs = SitePages::compareSlugs();
        $hub = self::words('index');

        if ($slugs === [] || $hub === [] || ! View::exists('site.compare')) {
            return self::gone();
        }

        return response()->view('site.compare', [
            'title' => SitePages::title((string) ($hub['title'] ?? '')),
            'meta' => ['title' => SitePages::title((string) ($hub['title'] ?? '')), 'description' => (string) ($hub['description'] ?? ''), 'url' => Hosts::publicUrl().'/compare'],
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'pages' => array_map(static fn (string $slug): array => self::page($slug, $request), $slugs),
            'common' => self::common($request),
        ], 200, self::headers());
    }

    public function show(Request $request, string $slug): Response
    {
        if (! in_array($slug, SitePages::compareSlugs(), true) || ! View::exists('site.compare-page')) {
            return self::gone();
        }

        $page = self::page($slug, $request);

        return response()->view('site.compare-page', [
            'title' => SitePages::title((string) ($page['title'] ?? '')),
            'meta' => ['title' => SitePages::title((string) ($page['title'] ?? '')), 'description' => (string) ($page['description'] ?? ''), 'url' => Hosts::publicUrl().'/compare/'.$slug],
            'page' => $page,
            'common' => self::common($request),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
        ], 200, self::headers());
    }

    /**
     * A page's words, with its slug and our prices filled in (:price_month, :price_year) in the
     * visitor's currency, as the pricing page shows them; never typed into the words.
     *
     * @return array<string, mixed>
     */
    private static function page(string $slug, Request $request): array
    {
        return [...SitePages::fill(self::words('pages.'.$slug), self::prices($request)), 'slug' => $slug];
    }

    /**
     * Stackapella's own column, the same on every page (site.compare.common), prices filled in.
     *
     * @return array<string, mixed>
     */
    private static function common(Request $request): array
    {
        return SitePages::fill(self::words('common'), self::prices($request));
    }

    /**
     * @return array{':price_month': string, ':price_year': string}
     */
    private static function prices(Request $request): array
    {
        $price = Prices::of(Prices::currency($request));

        return [':price_month' => $price['month'], ':price_year' => $price['year']];
    }

    /**
     * Words under site.compare (the landing agent's): the hub's `index` (title, description) and
     * each page's; empty until they're written.
     *
     * @return array<string, mixed>
     */
    private static function words(string $key): array
    {
        $words = Lang::get('site.compare.'.$key);

        return is_array($words) ? $words : [];
    }

    /**
     * @return array<string, string>
     */
    private static function headers(): array
    {
        // The prices follow the visitor's country, so a cache keeps a copy per country.
        $headers = [...SongPagesController::headers(), 'Vary' => 'CF-IPCountry, Accept-Language'];

        // Not in search until the switch is on, whatever the view says.
        if (! app(Flags::class)->on('compare_pages')) {
            $headers['X-Robots-Tag'] = 'noindex';
        }

        return $headers;
    }

    private static function gone(): Response
    {
        return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, self::headers());
    }
}
