<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Facades\Lang;

/**
 * What a song part page hands the frontend's part player (js/public/part-player.js, mounted by
 * js/public/song-part-page.js): the song and the part, where "Open in the app" leads (left out here: the page has its
 * own button) and the player's words from lang.
 */
final class SongPartPage
{
    /**
     * @param  array<string, mixed>  $page  SongParts::page()
     * @return array<string, mixed>
     */
    public static function player(array $page): array
    {
        return [
            'slug' => ((array) $page['song'])['slug'],
            'part' => ((array) $page['part'])['key'],
            'startUrl' => '',
            'words' => (array) Lang::get('site.song_part.player'),
        ];
    }
}
