<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SiteLanguage;
use App\Support\Languages\LanguageMenu;
use App\Support\Languages\Languages;
use App\Support\Pages\Landing;
use App\Support\Pages\NextClaude;
use App\Support\Pages\NextFinal;
use App\Support\Pages\Prices;
use App\Support\Site\SitePages;
use App\Support\Site\Teams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * /next-final and /es/next-final: the candidate for the final next landing page, /next-claude with what was worth
 * taking from the other three (Hillel, 2026-11-15), live so he can try it beside them. Private until one is picked: never indexed (the header here and the page's own robots tag), in no
 * sitemap, no language's list (so no hreflang) and linked from nowhere; counted only as sv_next_final. The landing
 * at / is untouched.
 */
final class NextFinalController extends Controller
{
    /** The languages the page is written in. */
    private const LANGUAGES = ['en', 'es'];

    public function english(Request $request): Response
    {
        return $this->page($request, 'en');
    }

    /**
     * The Spanish page, by its own route: the languages' middleware serves only the pages in config/languages.php,
     * and being listed there would give this one hreflang and a place in the sitemap.
     */
    public function spanish(Request $request): Response
    {
        return $this->page($request, 'es');
    }

    private function page(Request $request, string $language): Response
    {
        $before = App::getLocale();
        App::setLocale($language);

        try {
            $ref = Landing::ref($request->query('ref')) ?? NextFinal::REF;
            $price = Prices::of(Prices::currency($request));
            $plans = Landing::plans();
            $path = ($language === 'en' ? '' : '/'.$language).NextFinal::PATH;
            $offered = app(Languages::class)->offered();
            // The page in each language it's written in, while that language is open (or it's the one being read).
            $languages = array_values(array_filter(self::LANGUAGES, static fn (string $code): bool => $code === 'en' || $code === $language || in_array($code, $offered, true)));

            $response = response()->view('pages.next-final', [
                'title' => __('next_final.title'),
                'description' => __('next_final.description'),
                'stage' => NextFinal::stage(),
                'links' => Landing::links($ref),
                'counts' => NextClaude::counts(),
                'price' => $price,
                'plans' => $plans,
                'questions' => NextClaude::questions($plans['freeSongs'], $price['yearAsMonth']),
                'toolsUrl' => SitePages::toolsHome(),
                // Starting a choir group takes Plus while this switch is on: the choir scene says so.
                'teams' => Teams::on(),
                'languages' => array_map(static fn (string $code): array => [
                    'code' => $code,
                    'name' => LanguageMenu::NAMES[$code],
                    'url' => ($code === 'en' ? '' : '/'.$code).NextFinal::PATH,
                    'current' => $code === $language,
                ], $languages),
            ], 200, [
                ...SongPagesController::headers(),
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => 'no-cache',
                'Vary' => 'Cookie, CF-IPCountry, Accept-Language',
            ]);

            // Its links kept in its language; the page is in no language's list, so no alternates are named.
            return SiteLanguage::finish($response, $language, $path);
        } finally {
            App::setLocale($before);
        }
    }
}
