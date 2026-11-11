<?php

declare(strict_types=1);

namespace App\Support\Languages;

use App\Http\Controllers\Public\InfoPagesController;
use App\Http\Controllers\Public\LanguageNotFoundController;
use App\Http\Middleware\SiteLanguage;
use App\Support\Http\Hosts;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * The public site's pages in other languages (config/languages.php): each listed page again under /<lang>/, with the
 * same controller, in that language. A language's pages answer while its switch is on (locally and in tests, always,
 * to check a draft). Links from such a page to another listed page stay in the language; every listed page names its
 * other languages (hreflang), and the sitemap lists them.
 */
final class SiteLanguages
{
    /** @var array<string, array<string, string>>|null each language's page regexes by route name, from the English routes */
    private static ?array $patterns = null;

    /**
     * Each language's copies of its listed pages; the English pages gain the same middleware, for their hreflang.
     * Called with the routes, before the PWA's fallback.
     */
    public static function register(Router $router): void
    {
        $router->getRoutes()->refreshNameLookups();

        foreach (array_keys(Languages::SWITCHES) as $language) {
            // The home page: the landing, which / serves through the app's shell rather than a route.
            if (in_array('home', self::names($language), true)) {
                $router->get($language, [InfoPagesController::class, 'landing'])->middleware(['nexus', 'throttle:public-pages', SiteLanguage::class.':'.$language])->name($language.'.home');
            }

            foreach (self::names($language) as $name) {
                $route = $router->getRoutes()->getByName($name);

                if ($route === null) {
                    continue;
                }

                if (! in_array(SiteLanguage::class.':en', $route->middleware(), true)) {
                    $route->middleware(SiteLanguage::class.':en');
                }

                $router->get($language.'/'.ltrim($route->uri(), '/'), $route->getAction('uses'))
                    ->where($route->wheres)
                    ->setDefaults($route->defaults)
                    ->middleware([...array_values(array_diff($route->middleware(), [SiteLanguage::class.':en'])), SiteLanguage::class.':'.$language])
                    ->name($language.'.'.$name);
            }

            // Everything else under the language is not found, never the app's shell.
            $router->get($language.'/{rest?}', LanguageNotFoundController::class)->where('rest', '.*')->middleware('throttle:public-pages')->name($language.'.missing');
        }

        self::$patterns = null;
    }

    /**
     * @return list<string>
     */
    public static function names(string $language): array
    {
        return array_values(array_filter((array) config('languages.site.'.$language), 'is_string'));
    }

    /**
     * Whether an English path is a page this language serves.
     */
    public static function serves(string $language, string $path): bool
    {
        foreach (self::patterns()[$language] ?? [] as $name => $regex) {
            if (preg_match($regex, '/'.ltrim($path, '/'), $match) === 1) {
                return self::written($language, (string) $name, $match);
            }
        }

        return false;
    }

    /**
     * Whether a page whose words are a whole file in each language (a guide: resources/guides/<lang>/<slug>.md) has
     * its file in this one; any other listed page is in it by its maps.
     *
     * @param  array<int|string, string>  $match
     */
    private static function written(string $language, string $name, array $match): bool
    {
        return match ($name) {
            'guides.page' => is_string($match['slug'] ?? null) && is_file(config('content.guides').'/'.$language.'/'.$match['slug'].'.md'),
            default => true,
        };
    }

    /**
     * The languages a page is in, English first, for its hreflang: only those open to everyone, and only when
     * there's more than English.
     *
     * @return list<string>
     */
    public static function alternatesOf(string $englishPath): array
    {
        $languages = array_values(array_filter(app(Languages::class)->offered(), static fn (string $language): bool => $language === 'en' || self::serves($language, $englishPath)));

        return count($languages) > 1 ? $languages : [];
    }

    /**
     * A page's address in a language.
     */
    public static function url(string $language, string $englishPath): string
    {
        $path = '/'.ltrim($englishPath, '/');

        return Hosts::publicUrl().($language === 'en' ? $path : '/'.$language.($path === '/' ? '' : $path));
    }

    /**
     * The English path of a request's page: its own path, without a language's prefix.
     */
    public static function englishPath(string $path, string $language): string
    {
        $path = '/'.ltrim($path, '/');

        return $language === 'en' ? $path : ((string) preg_replace('#^/'.preg_quote($language, '#').'(?=/|$)#', '', $path) ?: '/');
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function patterns(): array
    {
        if (self::$patterns !== null) {
            return self::$patterns;
        }

        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();
        $patterns = [];

        foreach (array_keys(Languages::SWITCHES) as $language) {
            $patterns[$language] = [];

            foreach (self::names($language) as $name) {
                if ($name === 'home') {
                    $patterns[$language][$name] = '{^/$}';

                    continue;
                }

                $route = $routes->getByName($name);

                if ($route instanceof Route) {
                    $patterns[$language][$name] = $route->getCompiled()?->getRegex() ?? $route->toSymfonyRoute()->compile()->getRegex();
                }
            }
        }

        return self::$patterns = $patterns;
    }
}
