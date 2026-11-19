<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * The Yodeling course page's drawings (site/courses/yodeling, Hillel's design of 2026-11-18: a climb up a Swiss hiking
 * trail), kept out of the view. The call is the course's own hook, "yo-hee-o" (lesson 1's Flip or slide?, yodel-1-hear):
 * a beat in chest voice a fifth below the flip, a beat in head voice a step above it, two beats back down in chest;
 * js/public/yodeling.js plays the same notes. The panorama draws it over the two voices' lines, with the valley's two
 * echoes smaller and further off. And the alphorn's natural notes 8 to 12 of one tube, each placed by its cents above
 * the 8th (1200 × log2(n / 8)), over the piano's keys.
 */
final class YodelingPage
{
    /** The hook as the course has it: [beat, length in beats, semitones from the flip, head voice]. */
    public const HOOK = [[0, 1, -7, false], [1, 1, 2, true], [2, 2, -7, false]];

    /** The panorama's two lines: head voice above the treeline, chest voice on the valley floor (a 1440 by 320 box). */
    public const HEAD_Y = 72;

    public const CHEST_Y = 152;

    /** The hook's tempo (lesson 1's The hook in time), and how many beats the valley takes to send it back. */
    public const TEMPO = 72;

    public const ECHO_BEATS = 2;

    /**
     * The hook drawn from $x, $beat units a beat, its height scaled by $rise (1 is the full gap between the lines), each
     * piece [path, 'chest' or 'head', when it sounds in seconds]: a note's run, and the jump into it in the voice it lands
     * in. $echo is which answer from the valley it is (0 the call itself), each one ECHO_BEATS later.
     *
     * @return list<array{0: string, 1: string, 2: float}>
     */
    public static function call(int $x, int $beat, float $rise = 1.0, int $echo = 0): array
    {
        $head = (int) round(self::CHEST_Y - (self::CHEST_Y - self::HEAD_Y) * $rise);
        $pieces = [];
        $was = null;

        foreach (self::HOOK as [$at, $length, , $isHead]) {
            $y = $isHead ? $head : self::CHEST_Y;
            $from = $x + $at * $beat;
            $voice = $isHead ? 'head' : 'chest';
            $jump = $was !== null && $was !== $y ? 'M'.$from.' '.$was.' V'.$y.' ' : '';
            $pieces[] = [$jump.'M'.$from.' '.$y.' H'.($from + $length * $beat), $voice, round(($at + $echo * self::ECHO_BEATS) * 60 / self::TEMPO, 3)];
            $was = $y;
        }

        return $pieces;
    }

    /**
     * The signpost's board: the course's lessons and its minutes in all (`:lessons`, `:minutes` in $words), or only the
     * lessons (`:n` in $lessonsOnly) when the course has no length.
     *
     * @param  array{lessons: int, minutes?: int|null}  $outline
     */
    public static function size(array $outline, string $words, string $lessonsOnly): string
    {
        $minutes = $outline['minutes'] ?? null;

        return $minutes === null
            ? str_replace(':n', (string) $outline['lessons'], $lessonsOnly)
            : str_replace([':lessons', ':minutes'], [(string) $outline['lessons'], (string) $minutes], $words);
    }

    /**
     * Whether a unit's sign has a mountain path's white-red-white tip: the harder second half of the trail (units 3 and 4,
     * rhythm and the fast roll), as a real sign marks the steeper path.
     */
    public static function mountain(int $unit): bool
    {
        return $unit > 2;
    }

    /**
     * The alphorn's natural notes 8 to 12, each with its cents above the 8th, rounded.
     *
     * @return array<int, int>
     */
    public static function hornNotes(): array
    {
        $notes = [];

        foreach (range(8, 12) as $n) {
            $notes[$n] = (int) round(1200 * log($n / 8, 2));
        }

        return $notes;
    }

    /**
     * The piano's keys from C to G by their cents above C.
     *
     * @return array<string, int>
     */
    public static function keys(): array
    {
        return ['C' => 0, 'D' => 200, 'E' => 400, 'F' => 500, 'F♯' => 600, 'G' => 700];
    }
}
