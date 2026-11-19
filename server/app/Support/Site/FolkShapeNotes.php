<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * The Folk and early music course page's notes (site/courses/folk-and-early-music, Hillel's design of 2026-11-18): an old
 * printed ballad sheet whose notes are drawn as shape notes, the four shapes of the American singing-school books (fa a
 * triangle, sol an oval, la a square, mi a diamond). A note's shape is its syllable in the key it is read in.
 *
 * Drunken Sailor's notes come from its song template (database/templates/drunken-sailor.json, the soprano's, in E
 * Dorian, read in two sharps: E is sol, B la, C sharp mi, D fa).
 *
 * - opening(): its first line (beats 0 to 8), "What shall we do with a drunk-en sai-lor", for the broadside's head.
 * - line(): Now Playing's line, the third (beats 16 to 24), its notes, words and tempo. Each note is drawn two ways: as
 *   the tune goes (the bright note, Dorian's raised sixth, read in two sharps, so the sixth is mi) and as plain minor
 *   would sing it (the sixth a half step lower, read in one sharp, so it is fa, and D and E become sol and la). The
 *   template's last note stops early for a breath; here it is held to the end of the line, as the print has it
 *   (docs/courses/folk.md), so the line ends on its note.
 * - ladders(): the two old scales as rising steps, Dorian and Mixolydian, the note the old tune changes marked, and where
 *   the familiar scale (minor, major) would sing it as a ghost.
 *
 * A file that's missing gives an empty line, never an error.
 */
final class FolkShapeNotes
{
    /** Each syllable's semitones above do, in the four-shape system. */
    private const SHAPES = [0 => 'fa', 2 => 'sol', 4 => 'la', 5 => 'fa', 7 => 'sol', 9 => 'la', 11 => 'mi'];

    /** Where do sits below a mode's home note, in semitones: Dorian is re-based, minor la-based, Mixolydian sol-based. */
    private const DO_BELOW = ['dorian' => 2, 'minor' => 9, 'mixolydian' => 7, 'major' => 0];

    private const SONG = 'database/templates/drunken-sailor.json';

    /**
     * The first line's notes, each with its semitones above the line's lowest note and its shape.
     *
     * @return list<array{semi: int, shape: string, word: string}>
     */
    public static function opening(): array
    {
        $song = self::json(base_path(self::SONG));
        $notes = self::notes($song, 0.0, 8.0);
        if ($notes === []) {
            return [];
        }
        $tonic = self::tonicUnder($notes[0]['midi'], self::homeOf((string) ($song['key'] ?? 'Em')));
        $low = min(array_column($notes, 'midi'));

        return array_map(static fn (array $n): array => ['semi' => $n['midi'] - $low, 'shape' => self::shape($n['midi'] - $tonic, 'dorian'), 'word' => $n['word']], $notes);
    }

    /**
     * @return array{tempo: int, home: int, span: float, notes: list<array{at: float, len: float, midi: int, dark: int, word: string, semi: int, darkSemi: int, sixth: bool, brightShape: string, darkShape: string}>}
     */
    public static function line(): array
    {
        $song = self::json(base_path(self::SONG));
        $tempo = (int) ($song['tempo'] ?? 88);
        $raw = self::notes($song, 16.0, 24.0);
        if ($raw === []) {
            return ['tempo' => $tempo, 'home' => 64, 'span' => 8.0, 'notes' => []];
        }

        $last = count($raw) - 1;
        $raw[$last]['len'] = 8.0 - $raw[$last]['at'];
        $base = $raw[0]['midi'];
        // The home note just below the line (E4), as voices.js modeDrone takes it: the drone sounds an octave under it.
        $tonic = self::tonicUnder($base, self::homeOf((string) ($song['key'] ?? 'Em')));

        $notes = [];
        foreach ($raw as $note) {
            $sixth = (($note['midi'] - $tonic) % 12 + 12) % 12 === 9;
            $dark = $sixth ? $note['midi'] - 1 : $note['midi'];
            $notes[] = [
                'at' => $note['at'],
                'len' => $note['len'],
                'midi' => $note['midi'],
                'dark' => $dark,
                'word' => $note['word'],
                'semi' => $note['midi'] - $base,
                'darkSemi' => $dark - $base,
                'sixth' => $sixth,
                'brightShape' => self::shape($note['midi'] - $tonic, 'dorian'),
                'darkShape' => self::shape($dark - $tonic, 'minor'),
            ];
        }

        return ['tempo' => $tempo, 'home' => $tonic, 'span' => 8.0, 'notes' => $notes];
    }

    /**
     * The two old scales from the home note up to its octave: each step's semitones and shape, the step the old tune
     * changes, and the familiar scale's note there.
     *
     * @return array{bright: array{steps: list<array{semi: int, shape: string, changed: bool}>, ghost: array{at: int, semi: int, shape: string}}, soft: array{steps: list<array{semi: int, shape: string, changed: bool}>, ghost: array{at: int, semi: int, shape: string}}}
     */
    public static function ladders(): array
    {
        return [
            'bright' => self::ladder('dorian', [0, 2, 3, 5, 7, 9, 10, 12], 5, 8, 'minor'),
            'soft' => self::ladder('mixolydian', [0, 2, 4, 5, 7, 9, 10, 12], 6, 11, 'major'),
        ];
    }

    /** The shape of a note `$above` semitones over the home note, in `$mode` ('dorian', 'minor', 'mixolydian' or 'major'). */
    public static function shape(int $above, string $mode): string
    {
        $fromDo = ((($above + (self::DO_BELOW[$mode] ?? 0)) % 12) + 12) % 12;

        return self::SHAPES[$fromDo] ?? 'la';
    }

    /**
     * @param  list<int>  $semis
     * @return array{steps: list<array{semi: int, shape: string, changed: bool}>, ghost: array{at: int, semi: int, shape: string}}
     */
    private static function ladder(string $mode, array $semis, int $changed, int $usual, string $familiar): array
    {
        return [
            'steps' => array_map(static fn (int $semi, int $i): array => ['semi' => $semi, 'shape' => self::shape($semi, $mode), 'changed' => $i === $changed], $semis, array_keys($semis)),
            'ghost' => ['at' => $changed, 'semi' => $usual, 'shape' => self::shape($usual, $familiar)],
        ];
    }

    /**
     * The soprano's notes from `$from` up to `$to` (beats), their beats counted from `$from`.
     *
     * @param  array<array-key, mixed>  $song
     * @return list<array{at: float, len: float, midi: int, word: string}>
     */
    private static function notes(array $song, float $from, float $to): array
    {
        $notes = [];
        foreach ((array) ($song['parts'] ?? []) as $part) {
            if (! is_array($part) || ($part['key'] ?? null) !== 'soprano') {
                continue;
            }
            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                $at = is_array($note) ? (float) ($note['beat'] ?? -1) : -1.0;
                if (is_array($note) && $at >= $from && $at < $to) {
                    $notes[] = ['at' => $at - $from, 'len' => (float) ($note['length'] ?? 1), 'midi' => (int) ($note['pitch'] ?? 60), 'word' => (string) ($note['lyric'] ?? '')];
                }
            }
        }

        return $notes;
    }

    /** The home note (a pitch class) at or just below `$midi`. */
    private static function tonicUnder(int $midi, int $home): int
    {
        return $midi - ((($midi - $home) % 12) + 12) % 12;
    }

    /** A key like "Em" or "F#m": its home note as a pitch class. */
    private static function homeOf(string $key): int
    {
        $names = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];
        $pc = $names[strtoupper(substr($key, 0, 1))] ?? 4;
        $pc += match (substr($key, 1, 1)) {
            '#' => 1,
            'b' => -1,
            default => 0,
        };

        return ($pc + 12) % 12;
    }

    /** @return array<array-key, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
