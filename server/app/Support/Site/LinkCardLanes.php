<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\Template;

/**
 * A song collection's link card's "part lanes" (Hillel's "Genre banners redesign", 2026-11-11: "shared part lanes
 * motif"): one real song's opening (from the beat every part has come in), its parts as four lanes of note-bars, one part lit, captioned with the song, the part
 * and the song's length. Drawn from the song file's own notes, so the picture is true (tool/link_cards.py draws it).
 */
final class LinkCardLanes
{
    /** Each collection's song, the part it lights (a different part on each card) and how many beats the lanes show:
     * a round's quick notes need a shorter window to read as bars. */
    public const SONGS = [
        'carols' => ['silent-night', 'soprano', 16],
        'hymns' => ['abide-with-me', 'alto', 16],
        'spirituals' => ['deep-river', 'tenor', 16],
        'sea-shanties' => ['drunken-sailor', 'bass', 16],
        'rounds' => ['row-row-row', 'alto', 8],
        'jewish-songs' => ['maoz-tzur', 'soprano', 16],
    ];

    /**
     * The lanes for a collection's card, or null when its song isn't in the library.
     *
     * @return array{song: string, part: string, length: string, lit: int, lanes: list<list<array{x: float, w: float, y: float}>>}|null
     */
    public static function for(string $theme): ?array
    {
        [$slug, $lit, $window] = self::SONGS[$theme] ?? [null, null, 16];
        $template = $slug === null ? null : Template::query()->with('currentVersion')->where('slug', $slug)->first();
        $data = (array) ($template->currentVersion->data ?? []);
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        if ($template === null || $parts === []) {
            return null;
        }

        // The window opens once every part has come in (a shanty's crew answers after the shantyman), so no lane is empty.
        $from = 0.0;
        foreach ($parts as $part) {
            $beats = array_map(static fn (array $n): float => (float) ($n['beat'] ?? 0), array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array')));
            $from = max($from, $beats === [] ? 0.0 : min($beats));
        }
        $from = floor($from);

        $end = 0.0;
        $lanes = [];
        $litIndex = 0;
        $litName = '';
        foreach ($parts as $i => $part) {
            $notes = array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'));
            foreach ($notes as $note) {
                $end = max($end, (float) ($note['beat'] ?? 0) + (float) ($note['length'] ?? 1));
            }
            $opening = array_values(array_filter($notes, static fn (array $n): bool => (float) ($n['beat'] ?? 0) >= $from && (float) ($n['beat'] ?? 0) < $from + $window));
            $pitches = array_map(static fn (array $n): int => (int) ($n['pitch'] ?? 60), $opening);
            [$low, $high] = $pitches === [] ? [60, 60] : [min($pitches), max($pitches)];
            // x and w as fractions of the window, y from 0 (its lowest note) to 1 (its highest).
            $lanes[] = array_map(static fn (array $n): array => [
                'x' => round(((float) $n['beat'] - $from) / $window, 4),
                'w' => round(min((float) ($n['length'] ?? 1), $from + $window - (float) $n['beat']) / $window, 4),
                'y' => $high === $low ? 0.5 : round(((int) ($n['pitch'] ?? 60) - $low) / ($high - $low), 3),
            ], $opening);
            if (($part['key'] ?? null) === $lit) {
                $litIndex = $i;
                $litName = (string) ($part['name'] ?? $lit);
            }
        }
        $seconds = (int) round($end * 60 / max(1.0, (float) ($data['tempo'] ?? 80)));

        // The title without a note in brackets ("Row, Row, Row Your Boat (round)"), to fit beside the part and the length.
        return ['song' => trim((string) preg_replace('/\\s*\\([^)]*\\)$/', '', (string) $template->title)), 'part' => $litName, 'length' => intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT), 'lit' => $litIndex, 'lanes' => array_slice($lanes, 0, 4)];
    }
}
