<?php

declare(strict_types=1);

namespace App\Support\Tools;

/**
 * The note frequency chart's rows, rendered in the page so search and readers without the script get the whole
 * table (docs/tools/seo-2.md, "Note frequency chart"): C0 to B8 (MIDI 12 to 119), equal temperament from A4,
 * two decimals, middle C = C4 = MIDI 60. The same values as the tool's noteRows() and hzText()
 * (js/public/tools/note-frequencies.js).
 */
final class NoteChart
{
    public const FIRST = 12;

    public const LAST = 119;

    private const SHARPS = ['C', 'C♯', 'D', 'D♯', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'A♯', 'B'];

    private const FLATS = ['C', 'D♭', 'D', 'E♭', 'E', 'F', 'G♭', 'G', 'A♭', 'A', 'B♭', 'B'];

    /**
     * Each note from C0 to B8: its MIDI number, its name with the octave (a black key both ways, "C♯4 / D♭4") and
     * its frequency in Hz to two decimals.
     *
     * @return list<array{midi: int, name: string, hz: string}>
     */
    public static function rows(float $a4 = 440.0): array
    {
        $rows = [];
        for ($midi = self::FIRST; $midi <= self::LAST; $midi++) {
            $octave = intdiv($midi, 12) - 1;
            $sharp = self::SHARPS[$midi % 12].$octave;
            $flat = self::FLATS[$midi % 12].$octave;
            $rows[] = [
                'midi' => $midi,
                'name' => $sharp === $flat ? $sharp : $sharp.' / '.$flat,
                'hz' => number_format($a4 * 2 ** (($midi - 69) / 12), 2, '.', ''),
            ];
        }

        return $rows;
    }
}
