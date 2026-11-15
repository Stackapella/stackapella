<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SiteLanguage;
use App\Support\Languages\LanguageMenu;
use App\Support\Languages\Languages;
use App\Support\Pages\Landing;
use App\Support\Pages\NextClaude;
use App\Support\Pages\Prices;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * /next-claude and /es/next-claude: one of four candidates for the next landing page, live so Hillel can try each
 * (2026-11-15). Private until one is picked: never indexed (the header here and the page's own robots tag), in no
 * sitemap, no language's list (so no hreflang) and linked from nowhere; counted only as sv_next_claude. The landing
 * at / is untouched.
 */
final class NextClaudeController extends Controller
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
            $ref = Landing::ref($request->query('ref')) ?? NextClaude::REF;
            $price = Prices::of(Prices::currency($request));
            $plans = Landing::plans();
            $path = ($language === 'en' ? '' : '/'.$language).NextClaude::PATH;
            $offered = app(Languages::class)->offered();
            // The page in each language it's written in, while that language is open (or it's the one being read).
            $languages = array_values(array_filter(self::LANGUAGES, static fn (string $code): bool => $code === 'en' || $code === $language || in_array($code, $offered, true)));

            $response = response()->view('pages.next-claude', [
                'title' => __('next_claude.title'),
                'description' => __('next_claude.description'),
                'stage' => NextClaude::stage(),
                'links' => Landing::links($ref),
                'counts' => NextClaude::counts(),
                'price' => $price,
                'plans' => $plans,
                'questions' => NextClaude::questions($plans['freeSongs'], $price['yearAsMonth']),
                'toolsUrl' => SitePages::toolsHome(),
                'languages' => array_map(static fn (string $code): array => [
                    'code' => $code,
                    'name' => LanguageMenu::NAMES[$code],
                    'url' => ($code === 'en' ? '' : '/'.$code).NextClaude::PATH,
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
