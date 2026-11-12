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

    /**
     * A song's title as a page shows it: its own title in the page's language where it has one (song_titles: "Frère
     * Jacques" for Are You Sleeping in Spanish; the courses lead, for the coordinator), else a round's note in it
     * ("(canon)").
     */
    public static function title(string $title, ?string $slug = null): string
    {
        $locale = App::getLocale();
        if ($locale === 'en') {
            return $title;
        }
        $own = $slug !== null ? (self::titles($slug)[$locale] ?? null) : null;

        return $own ?? (string) preg_replace('/\s*\(round\)$/', ' '.__('pages.songs.round_note'), $title);
    }

    /**
     * A song's own titles in other languages (resources/data/song_titles/<lang>.json, by slug, as the app's
     * js/song-titles.js keys them), for the app to search and show.
     *
     * @return array<string, string>
     */
    public static function titles(string $slug): array
    {
        $titles = [];
        foreach (glob(resource_path('data/song_titles/*.json')) ?: [] as $file) {
            $language = basename($file, '.json');
            self::$maps['titles/'.$language] ??= array_filter((array) json_decode((string) file_get_contents($file), true), 'is_string');
            if (isset(self::$maps['titles/'.$language][$slug])) {
                $titles[$language] = self::$maps['titles/'.$language][$slug];
            }
        }

        return $titles;
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
