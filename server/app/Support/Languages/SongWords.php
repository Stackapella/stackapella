<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Illuminate\Support\Facades\App;

/**
 * A song file's own words in the page's language (the courses lead's maps, 2026-11-12): its description (under its
 * title), its credits (who wrote the words and the tune) and its genres (the chips). Each language has a map per kind,
 * resources/data/song_<kind>/<lang>.json: descriptions and credits from the English to its own, so a line that changes
 * shows in English until its map has the new one, never stale; genres from the genre's key to its label.
 */
final class SongWords
{
    /** @var array<string, array<string, string>> each map, read once ("<kind>/<lang>") */
    private static array $maps = [];

    public static function description(string $english): string
    {
        return self::map('descriptions')[$english] ?? $english;
    }

    public static function credit(string $english): string
    {
        return self::map('credits')[$english] ?? $english;
    }

    /** A song's title as a page shows it: a round's note after it in the page's language ("(canon)" in Spanish). */
    public static function title(string $title): string
    {
        return App::getLocale() === 'en' ? $title : (string) preg_replace('/\s*\(round\)$/', ' '.__('pages.songs.round_note'), $title);
    }

    /** A genre's chip ("hymn" is "Hymn" in English, "Himno" in Spanish). */
    public static function genre(string $key): string
    {
        return self::map('genres')[strtolower($key)] ?? self::map('genres')[$key] ?? ucfirst($key);
    }

    /** @return array<string, string> */
    private static function map(string $kind): array
    {
        $locale = App::getLocale();
        if ($locale === 'en' || preg_match('/^[a-z]{2}$/', $locale) !== 1) {
            return [];
        }
        if (! isset(self::$maps[$kind.'/'.$locale])) {
            $path = resource_path('data/song_'.$kind.'/'.$locale.'.json');
            $map = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$maps[$kind.'/'.$locale] = is_array($map) ? array_filter($map, 'is_string') : [];
        }

        return self::$maps[$kind.'/'.$locale];
    }
}
