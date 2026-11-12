<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Illuminate\Support\Facades\App;

/**
 * A song's part named in the page's language (the courses lead, 2026-11-12: "alto" reads as "high" in Spanish, so the
 * Spanish site says "contralto"). Only a part the song names as usual ("Alto" for its alto) is renamed; a song's own
 * name for a part stays its own. English keeps the song's names exactly.
 */
final class PartNames
{
    public static function name(string $key, string $songsName): string
    {
        if (App::getLocale() === 'en' || ! trans()->has('site.song_part.names.'.$key, 'en', false)) {
            return $songsName;
        }

        return $songsName === (string) __('site.song_part.names.'.$key, [], 'en') ? (string) __('site.song_part.names.'.$key) : $songsName;
    }
}
