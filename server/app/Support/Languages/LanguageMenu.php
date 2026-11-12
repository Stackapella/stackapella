<?php

declare(strict_types=1);

namespace App\Support\Languages;

use App\Support\Admin\StaffDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * The public site's language menu in its header (Hillel, 2026-11-12: "Header switcher"): each language open to
 * everyone, by its own name, linked to this page in it when it has one, else to that language's home. Shown only while
 * more than English is open, or to an admin previewing a language before its switch (StaffDevice::previews). Choosing
 * one sets the `sa_lang` preference (js/public/lang-menu.js); nothing ever moves a visitor by itself.
 */
final class LanguageMenu
{
    /** The cookie a choice is remembered in: only a language's code, read for a newcomer's language (RequestLanguage). */
    public const COOKIE = 'sa_lang';

    /** Each language by its own name, as a person looking for theirs reads it. */
    public const NAMES = ['en' => 'English', 'es' => 'Español', 'de' => 'Deutsch'];

    /**
     * @return array{current: string, choices: list<array{code: string, name: string, url: string, current: bool}>}|null
     */
    public static function for(Request $request): ?array
    {
        $languages = app(Languages::class)->offered();
        $current = App::getLocale();
        if (! in_array($current, $languages, true) || StaffDevice::previews($request)) {
            $languages = array_values(array_unique(['en', ...array_keys(array_filter((array) config('languages.site'), 'is_array'))]));
        }
        if (count($languages) < 2 || ! isset(self::NAMES[$current])) {
            return null;
        }

        $english = SiteLanguages::englishPath($request->getPathInfo(), $current);
        $choices = [];
        foreach ($languages as $code) {
            if (! isset(self::NAMES[$code])) {
                continue;
            }
            $here = $code === 'en' || SiteLanguages::serves($code, $english);
            $choices[] = ['code' => $code, 'name' => self::NAMES[$code], 'url' => SiteLanguages::url($code, $here ? $english : '/'), 'current' => $code === $current];
        }

        return count($choices) > 1 ? ['current' => $current, 'choices' => $choices] : null;
    }
}
