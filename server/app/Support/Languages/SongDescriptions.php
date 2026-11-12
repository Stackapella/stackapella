<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Illuminate\Support\Facades\App;

/**
 * A song's description (its file's `description`, under its title) in the page's language: each language's map from
 * the English description to its own (resources/data/song_descriptions/<lang>.json, the courses lead's, 2026-11-12).
 * Keyed by the English, so a description that changes shows in English until its map has the new one, never stale.
 */
final class SongDescriptions
{
    /** @var array<string, array<string, string>> each language's map, read once */
    private static array $maps = [];

    public static function of(string $english): string
    {
        $locale = App::getLocale();
        if ($locale === 'en' || $english === '' || preg_match('/^[a-z]{2}$/', $locale) !== 1) {
            return $english;
        }

        if (! isset(self::$maps[$locale])) {
            $path = resource_path('data/song_descriptions/'.$locale.'.json');
            $map = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$maps[$locale] = is_array($map) ? array_filter($map, 'is_string') : [];
        }

        return self::$maps[$locale][$english] ?? $english;
    }
}
