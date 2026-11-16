<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Http\Hosts;

/**
 * What a song page's score says about the song (docs/song-sheet-music-plan.md): its voicing, how many bars it has, its
 * time signature, and where its sheet music downloads start (the app, which asks a guest for a free account first).
 * The score itself is engraved in the browser (js/public/score.js) from the notes the player already reads.
 */
final class SongScore
{
    /** The files a singer can take away; "parts" is the picked part's own sheet. */
    public const array FILES = ['pdf', 'parts', 'musicxml', 'midi'];

    /**
     * @param  array<string, mixed>  $song  SongPage::of()'s (its parts and its kinds of song)
     * @param  float  $seconds  how long the whole song plays
     * @return array{voicing: string|null, bars: int|null, time: string, length: string, downloads: array<string, string>, song: array<string, mixed>}
     */
    public static function of(Template $template, array $song, float $seconds = 0.0): array
    {
        $data = (array) ($template->currentVersion->data ?? []);
        $time = is_string($data['timeSignature'] ?? null) ? $data['timeSignature'] : '4/4';
        $keys = array_values(array_filter(array_column(array_filter((array) ($song['parts'] ?? []), 'is_array'), 'key'), 'is_string'));
        $genre = array_values(array_filter((array) ($song['genre'] ?? []), 'is_string'));

        return [
            // SATB only where its parts are those four; a round says so; anything else is its count of parts.
            'voicing' => match (true) {
                SongParts::round($genre) => 'round',
                SongParts::satb($keys) => 'satb',
                default => null,
            },
            'bars' => self::bars($data, $time),
            'time' => $time,
            // Its length as the player counts it (whole seconds passed, as js/public/mixer.js's clock), so the facts and the
            // player never differ by a second.
            'length' => sprintf('%d:%02d', intdiv((int) floor($seconds), 60), (int) floor($seconds) % 60),
            'song' => self::notes($data),
            'downloads' => array_combine(self::FILES, array_map(
                static fn (string $file): string => Hosts::app('/t/'.rawurlencode((string) $template->slug).'/sheet?'.http_build_query(['get' => $file, 'ref' => 'song-page'])),
                self::FILES,
            )),
        ];
    }

    /**
     * The song as the engraver reads it (js/engrave.js), and no more: its key, time, tempo and upbeat, and each part's
     * notes with their words and breaths. In the page itself, so showing the score reads nothing from the API (a guest's
     * reads are capped by network; the coordinator, 2026-11-16). Everyone may see every note (Hillel's choice).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function notes(array $data): array
    {
        $song = array_intersect_key($data, array_flip(['key', 'timeSignature', 'tempo', 'pickupBeats', 'meters']));
        $song['parts'] = array_values(array_map(static fn (array $part): array => [
            'key' => (string) ($part['key'] ?? ''),
            ...(($part['melody'] ?? false) === true ? ['melody' => true] : []),
            'guide' => ['notes' => array_values(array_map(
                static fn (array $note): array => array_intersect_key($note, array_flip(['beat', 'length', 'pitch', 'lyric', 'br'])),
                array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'),
            ))],
        ], array_filter((array) ($data['parts'] ?? []), 'is_array')));

        return $song;
    }

    /**
     * Its whole bars, the upbeat not counted, as the engraver numbers them (js/musicxml.js barsOf). Null for a song whose
     * time signature changes: its bars aren't one length, and the page then leaves the count out.
     *
     * @param  array<string, mixed>  $data
     */
    public static function bars(array $data, string $time): ?int
    {
        if (is_array($data['meters'] ?? null) && $data['meters'] !== []) {
            return null;
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $time, $m) !== 1 || (int) $m[1] === 0) {
            return null;
        }
        // A beat is a dotted quarter in x/8 and a quarter otherwise, as the song's notes count them.
        $perBar = (int) $m[2] === 8 ? (int) $m[1] / 3 : (int) $m[1];
        $pickup = (float) ($data['pickupBeats'] ?? 0);
        $end = 0.0;
        foreach (array_filter((array) ($data['parts'] ?? []), 'is_array') as $part) {
            foreach (array_filter((array) ($part['guide']['notes'] ?? []), 'is_array') as $note) {
                $end = max($end, (float) ($note['beat'] ?? 0) + (float) ($note['length'] ?? 1));
            }
        }

        return max(1, (int) ceil(($end - $pickup) / $perBar - 1e-6));
    }
}
