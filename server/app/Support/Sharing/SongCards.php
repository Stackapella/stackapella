<?php

declare(strict_types=1);

namespace App\Support\Sharing;

use App\Jobs\MakeSongCard;
use App\Models\Template;
use App\Services\Sharing\SongCard;
use App\Support\Http\Hosts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Songs' link cards (SongCard), made once and kept on the public media bucket by what they show:
 * the song, its version and the link's wording. A page asks for a card's URL; until it's made (a
 * queued job, a moment) the page gets the landing's card. Only our songs and the library's public
 * ones get a card: a private template's title never goes on a public file.
 */
final class SongCards
{
    public const WORDINGS = ['join', 'listen'];

    /** Bumped when the card's look changes, so every card is made again. */
    private const DESIGN = 1;

    private const CACHE = 'public, max-age=31536000, immutable';

    public function __construct(private readonly SongCard $card) {}

    public function url(Template $template, string $wording): string
    {
        if (! self::shows($template) || ! in_array($wording, self::WORDINGS, true)) {
            return self::fallback();
        }

        $key = $this->key($template, $wording);

        if (Cache::get('song-card:'.$key) === true) {
            return Storage::disk('media')->url($key);
        }

        // Asked for once while it's being made, however many pages ask meanwhile.
        if (Cache::add('song-card-making:'.$key, true, now()->addMinutes(10))) {
            MakeSongCard::dispatch($template->id, $wording);
        }

        return self::fallback();
    }

    /**
     * Make the card (again, if it's there already: the same bytes), and remember it's there.
     */
    public function make(Template $template, string $wording): void
    {
        if (! self::shows($template) || ! in_array($wording, self::WORDINGS, true)) {
            return;
        }

        $key = $this->key($template, $wording);
        $parts = count((array) ($template->currentVersion?->data['parts'] ?? []));
        $png = $this->card->render((string) __('site.meta.app.card.'.$wording), $template->title, $parts > 1 ? (string) __('sharing.song_card.parts', ['count' => $parts]) : '');
        Storage::disk('media')->put($key, $png, ['visibility' => 'public', 'ContentType' => 'image/png', 'CacheControl' => self::CACHE]);
        Cache::forever('song-card:'.$key, true);
        Cache::forget('song-card-making:'.$key);
    }

    /** The landing's card, while a song's is made or for a song that gets none. */
    public static function fallback(): string
    {
        return Hosts::public('/icons/og-landing.png');
    }

    private static function shows(Template $template): bool
    {
        return $template->deleted_at === null && ($template->isOfficial() || ($template->isPublic() && $template->hidden_at === null && $template->held_at === null));
    }

    /** By what the card shows, so a new title, version or wording is a new file (no stale CDN copy). */
    private function key(Template $template, string $wording): string
    {
        $version = (int) ($template->currentVersion->version ?? 0);

        return 'cards/songs/'.substr(hash('sha256', implode('|', [self::DESIGN, $template->id, $version, $wording, $template->title, app()->getLocale()])), 0, 24).'.png';
    }
}
