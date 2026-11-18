<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The Counterpoint course page's drawings (site/courses/counterpoint, after Hillel's design of 2026-11-18): square notes on
 * four-line staves, each note placed by its step of the scale, so the view only draws what this gives it. The round
 * (Row, Row, Row Your Boat, for three and, in Count the voices, for four), Fig. 1 (the course's own first-species
 * exercise, its interval figures counted from the notes), Split the tune, One voice or two, Seek and you shall find, the
 * history's voice lines and Tallis's forty. The sounds are js/public/counterpoint.js's, reading the same notes from the
 * page.
 */
final class CounterpointPage
{
    /** Row, Row, Row Your Boat: [start, length, semitones above do], in eighths of a 6/8 bar. */
    public const TUNE = [[0, 3, 0], [3, 3, 0], [6, 2, 0], [8, 1, 2], [9, 3, 4], [12, 2, 4], [14, 1, 2], [15, 2, 4], [17, 1, 5], [18, 6, 7], [24, 1, 12], [25, 1, 12], [26, 1, 12], [27, 1, 7], [28, 1, 7], [29, 1, 7], [30, 1, 4], [31, 1, 4], [32, 1, 4], [33, 1, 0], [34, 1, 0], [35, 1, 0], [36, 2, 7], [38, 1, 5], [39, 2, 4], [41, 1, 2], [42, 6, 0]];

    /** An eighth of the round, in seconds. */
    public const EIGHTH = 0.18;

    /** The round's drawing: its left edge and the width of an eighth. */
    public const X0 = 46;

    public const U = 14;

    /**
     * Fig. 1: the course's own first-species exercise (lesson cp-4-first, "First species", its listen step in
     * web/course/plus/counterpoint/course.json): the counterpoint over the cantus, in semitones from do.
     * tests/Feature/Site/CounterpointPageTest.php keeps them the same as the lesson's.
     */
    public const SPECIES = ['top' => [0, -1, -3, 0, -1, 2, 0, -1, 0], 'cantus' => [-12, -10, -7, -8, -5, -7, -8, -10, -12]];

    /** Seconds a pair of Fig. 1 sounds. */
    public const SPECIES_STEP = 0.9;

    /** Split the tune: a high tune and a low one taking turns (MIDI notes), drawn at these heights. */
    public const HIGH = [79, 81, 79, 76];

    public const LOW = [60, 62, 64, 62];

    private const RUN_Y = [28, 110, 20, 102, 28, 94, 40, 102];

    /** One voice or two: the same top line over a lower line in parallel fifths, then in contrary motion (MIDI). */
    public const FUSE = ['same' => [[72, 71, 69, 67], [65, 64, 62, 60]], 'opposite' => [[72, 71, 69, 67], [57, 59, 60, 64]]];

    /** Seek and you shall find: the page's own short line (MIDI notes, a beat each). */
    public const SEEK = [60, 65, 64, 69, 67, 69, 64, 60];

    /** The major scale's steps by semitone above do. */
    private const SCALE = [0 => 0, 2 => 1, 4 => 2, 5 => 3, 7 => 4, 9 => 5, 11 => 6];

    /** A heading with its key phrase in italic (the design's); a heading without the phrase is set plain. */
    public static function heading(string $text, string $part): HtmlString
    {
        if ($part === '' || ! str_contains($text, $part)) {
            return new HtmlString(e($text));
        }

        return new HtmlString(e(Str::beforeLast($text, $part)).'<em>'.e($part).'</em>'.e(Str::afterLast($text, $part)));
    }

    /** A note's step of the scale above do (octaves counted, so below do is negative), from its semitones above do. */
    public static function step(int $semitones): int
    {
        $within = (($semitones % 12) + 12) % 12;

        return 7 * intdiv($semitones - $within, 12) + self::SCALE[$within];
    }

    /**
     * The round for three: each voice on its staff (lines on re, fa, la and high do, 9px a step), two bars after the
     * last, each note with its time in seconds from the round's start and a bar holding a long one.
     *
     * @return list<array{n: int, lines: list<int>, label: int, notes: list<array{x: int, y: int, hold: int|null, at: float, len: float}>}>
     */
    public static function canon(): array
    {
        $voices = [];
        foreach ([0, 1, 2] as $v) {
            $base = 92 + $v * 118;
            $from = self::X0 + $v * 12 * self::U;
            $notes = [];
            foreach (self::TUNE as [$at, $length, $semis]) {
                $x = $from + $at * self::U;
                $notes[] = [
                    'x' => $x,
                    'y' => $base - self::step($semis) * 9,
                    'hold' => $length > 1 ? $x + $length * self::U - 4 : null,
                    'at' => round(($v * 12 + $at) * self::EIGHTH, 3),
                    'len' => round($length * self::EIGHTH, 3),
                ];
            }
            $voices[] = ['n' => $v + 1, 'lines' => array_map(static fn (int $line): int => $base - $line * 9, [1, 3, 5, 7]), 'label' => $base - 30, 'notes' => $notes];
        }

        return $voices;
    }

    /**
     * Fig. 1: the two lines as MIDI (do is middle C), each staff's four lines on every other step from just under its
     * lowest note (9px a step), and each pair: its place, its notes' heights and the interval it makes, counted in steps
     * of the scale (a third spans three).
     *
     * @return array{top: list<int>, low: list<int>, staves: list<array{name: string, voice: string, lines: list<int>, label: int}>, pairs: list<array{x: int, top: int, low: int, figure: int, tie: string, at: float}>}
     */
    public static function species(): array
    {
        $top = array_map(self::step(...), self::SPECIES['top']);
        $low = array_map(self::step(...), self::SPECIES['cantus']);
        $topHi = min($top) + 5;
        $lowHi = min($low) + 5;
        $topY = static fn (int $d): int => 24 + ($topHi - $d) * 9;
        $lowY = static fn (int $d): int => 150 + ($lowHi - $d) * 9;
        $pairs = [];
        foreach ($top as $i => $d) {
            $x = 84 + $i * 56;
            $yTop = $topY($d);
            $yLow = $lowY($low[$i]);
            $pairs[] = ['x' => $x, 'top' => $yTop, 'low' => $yLow, 'figure' => $d - $low[$i] + 1, 'tie' => "M{$x} ".($yTop + 9)." V100 M{$x} 128 V".($yLow - 9), 'at' => round($i * self::SPECIES_STEP, 3)];
        }
        $staff = static fn (callable $y, int $hi): array => array_map(static fn (int $k): int => $y($hi - $k), [0, 2, 4, 6]);

        return [
            'top' => array_map(static fn (int $s): int => 60 + $s, self::SPECIES['top']),
            'low' => array_map(static fn (int $s): int => 60 + $s, self::SPECIES['cantus']),
            'staves' => [
                ['name' => 'cp', 'voice' => 'cp-cp2-v2', 'lines' => $staff($topY, $topHi), 'label' => $topY($topHi - 3) + 6],
                ['name' => 'cf', 'voice' => 'cp-cp2-v1', 'lines' => $staff($lowY, $lowHi), 'label' => $lowY($lowHi - 3) + 6],
            ],
            'pairs' => $pairs,
        ];
    }

    /**
     * Split the tune: one zigzag through sixteen notes taking turns, and the high and low strands it falls into.
     *
     * @return array{zig: string, high: string, low: string, dots: list<array{x: int, y: int, high: bool}>}
     */
    public static function run(): array
    {
        $point = static fn (int $x, int $y): string => "{$x} {$y}";

        return [
            'zig' => 'M'.implode(' L', array_map(static fn (int $i): string => $point(24 + $i * 36, self::RUN_Y[$i % 8]), range(0, 15))),
            'high' => 'M'.implode(' L', array_map(static fn (int $i): string => $point(24 + $i * 72, self::RUN_Y[$i * 2 % 8]), range(0, 7))),
            'low' => 'M'.implode(' L', array_map(static fn (int $i): string => $point(60 + $i * 72, self::RUN_Y[$i * 2 % 8 + 1]), range(0, 7))),
            'dots' => array_map(static fn (int $i): array => ['x' => 24 + $i * 36, 'y' => self::RUN_Y[$i % 8], 'high' => $i % 2 === 0], range(0, 15)),
        ];
    }

    /**
     * One voice or two: the staff's lines and, for each way, its two lines (the top in ink, the lower in red), each
     * note by its step of the scale (C major, 8px a step, A3 at the foot).
     *
     * @return array{lines: list<int>, kinds: array<string, list<array{voice: string, points: string, notes: list<array{x: int, y: int}>}>>}
     */
    public static function fusion(): array
    {
        $y = static fn (int $m): int => 118 - (self::step($m - 60) - self::step(57 - 60)) * 8;
        $kinds = [];
        foreach (self::FUSE as $kind => $lines) {
            foreach ($lines as $v => $midi) {
                $notes = array_map(static fn (int $m, int $j): array => ['x' => 40 + $j * 84, 'y' => $y($m)], $midi, array_keys($midi));
                $kinds[$kind][] = ['voice' => $v === 0 ? 'cp-cp2-v1' : 'cp-cp2-v2', 'points' => self::points($notes), 'notes' => $notes];
            }
        }

        return ['lines' => array_map(static fn (int $k): int => 118 - $k * 8, [2, 4, 6, 8]), 'kinds' => $kinds];
    }

    /**
     * Seek and you shall find: the staff's lines, the line (ink, larger) and its copy (red), 12px a step, a beat 36px.
     *
     * @return array{lines: list<int>, voices: list<array{voice: string, copy: bool, size: int, points: string, notes: list<array{x: int, y: int}>}>}
     */
    public static function seek(): array
    {
        $notes = array_map(static fn (int $m, int $j): array => ['x' => 24 + $j * 36, 'y' => 104 - self::step($m - 60) * 12], self::SEEK, array_keys(self::SEEK));

        return [
            'lines' => array_map(static fn (int $k): int => 104 - $k * 12, [-1, 1, 3, 5]),
            'voices' => [
                ['voice' => 'cp-cp2-v1', 'copy' => false, 'size' => 14, 'points' => self::points($notes), 'notes' => $notes],
                ['voice' => 'cp-cp2-v2', 'copy' => true, 'size' => 10, 'points' => self::points($notes), 'notes' => $notes],
            ],
        ];
    }

    /**
     * Count the voices: the round for four, each voice on a staff of its own (4px a step), entering two bars after the
     * last across 84 eighths, and where each comes in on the time line.
     *
     * @return array{lane: float, voices: list<array{n: int, lines: list<int>, label: int, entry: float, notes: list<array{x: float, y: int, at: float, len: float}>}>}
     */
    public static function count(): array
    {
        $lane = 640 / 84;
        $voices = [];
        foreach ([0, 1, 2, 3] as $v) {
            $top = 12 + $v * 42;
            $notes = [];
            foreach (self::TUNE as [$at, $length, $semis]) {
                $notes[] = ['x' => round(44 + ($v * 12 + $at) * $lane - 3, 1), 'y' => $top + (7 - self::step($semis)) * 4 - 3, 'at' => round(($v * 12 + $at) * self::EIGHTH, 3), 'len' => round($length * self::EIGHTH, 3)];
            }
            $voices[] = ['n' => $v + 1, 'lines' => array_map(static fn (int $k): int => $top + $k * 4, [0, 2, 4, 6]), 'label' => $top + 20, 'entry' => round(44 + $v * 12 * $lane, 1), 'notes' => $notes];
        }

        return ['lane' => $lane, 'voices' => $voices];
    }

    /**
     * A year's voices: a line for each, each starting a step after the last with a small square.
     *
     * @return list<array{voice: string, x: int, y: int}>
     */
    public static function band(int $players): array
    {
        return array_map(static fn (int $i): array => ['voice' => 'cp-cp2-v'.($i % 3 + 1), 'x' => 4 + $i * 14, 'y' => 4 + $i * 6], range(0, max(0, $players - 1)));
    }

    /**
     * Tallis's forty: a line a voice in eight choirs of five, each coming in a step after the last. A picture of the
     * scoring, said so on the page, not the score's order of entry.
     *
     * @return list<array{voice: string, x: int, y: float}>
     */
    public static function forty(): array
    {
        return array_map(static fn (int $i): array => ['voice' => 'cp-cp2-v'.(intdiv($i, 5) % 3 + 1), 'x' => 6 + $i * 21, 'y' => 5 + $i * 4.4 + intdiv($i, 5) * 3], range(0, 39));
    }

    /**
     * @param  list<array{x: int, y: int}>  $notes
     */
    private static function points(array $notes): string
    {
        return implode(' ', array_map(static fn (array $n): string => $n['x'].','.$n['y'], $notes));
    }
}
