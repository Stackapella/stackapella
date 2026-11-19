<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * The Gospel and soul runs course page's drawings and sounds (site/courses/gospel-runs, Hillel's design of 2026-11-18),
 * from the course's own notes where it has them, so the view only loops and prints.
 *
 * - stairs(): the major pentatonic as stairs from C4 to C5, F and B left out as gaps. The course sets every line from
 *   the singer's own comfortable note (movable do), so the page shows it in C, do on C4.
 * - run(): lesson 1's run (gr-1-stairs, its scored "A-men"): la, so, mi, re, do at 60 to the quarter, two notes a second,
 *   and its two chords, IV moving home to I, placed in C. A course file that's missing gives the same run as written.
 * - speeds(): the featured deck's run, C5, A4, G4, E4, D4, C4, drawn as six notes in a panel (one panel a speed).
 * - smear(): the same run at 8 a second, clean and smeared, as its pitch line, one segment a note (the JS lights each).
 * - bend(): the blue note's lane, 300 to 400 cents above C4, its marks and the bend stopped at `$cents`.
 */
final class GospelRunsArt
{
    /** Do, for the page's drawings: C4. */
    public const DO = 60;

    /** The stairs from C4 to C5, in semitones above do; F (5) and B (11) are the gaps. */
    private const DEGREES = [0, 2, 4, 5, 7, 9, 11, 12];

    private const GAPS = [5, 11];

    /** The featured deck's run (MIDI), as js/public/gospel-runs.js plays it. */
    public const SPEEDS_RUN = [72, 69, 67, 64, 62, 60];

    /** Lesson 1's run as the course writes it (scale steps from do, beats at 60), if its file can't be read. */
    private const LESSON_RUN = [[0.0, 0.5, 9], [0.5, 0.5, 7], [1.0, 0.5, 4], [1.5, 0.5, 2], [2.0, 2.0, 0]];

    private const LESSON_CHORDS = [[0.0, -7, 4], [2.0, -12, 4]];

    /** Where the bend's slider starts: the major third, 400 cents above C4. */
    public const BEND_START = 400;

    /**
     * @return list<array{midi: int, step: int, gap: bool, rise: int}>
     */
    public static function stairs(): array
    {
        return array_map(static fn (int $step, int $i): array => [
            'midi' => self::DO + $step,
            'step' => $step,
            'gap' => in_array($step, self::GAPS, true),
            'rise' => $i,
        ], self::DEGREES, array_keys(self::DEGREES));
    }

    /**
     * Lesson 1's run and chords, in seconds: each note [midi, at, len], each chord [at, len, midis]; each note's scale
     * step above do and its stair (an index into stairs()); and the notes and chords as JSON, for the hero's button.
     *
     * @return array{notes: list<array{0: int, 1: float, 2: float}>, chords: list<array{0: float, 1: float, 2: list<int>}>, steps: list<int>, stairs: list<int>, data: string}
     */
    public static function run(): array
    {
        $tempo = 60;
        $notes = self::LESSON_RUN;
        $chords = self::LESSON_CHORDS;
        foreach (self::lessonSteps('gr-1-stairs') as $step) {
            $exercise = $step['exercise'] ?? null;
            if (($step['kind'] ?? '') !== 'play' || ! is_array($exercise) || ! is_array($exercise['notes'] ?? null)) {
                continue;
            }
            $tempo = max(1, (int) ($exercise['tempo'] ?? 60));
            $notes = array_map(static fn (array $n): array => [(float) ($n['beat'] ?? 0), (float) ($n['length'] ?? 0), (int) ($n['step'] ?? 0)], array_values(array_filter($exercise['notes'], 'is_array'))) ?: self::LESSON_RUN;
            $chords = array_map(static fn (array $c): array => [(float) ($c['beat'] ?? 0), (int) ($c['root'] ?? 0), ($c['quality'] ?? 'maj') === 'min' ? 3 : 4], array_values(array_filter((array) ($exercise['chords'] ?? []), 'is_array')));
            break;
        }

        $beat = 60 / $tempo;
        $end = max(array_map(static fn (array $n): float => $n[0] + $n[1], $notes));
        $out = ['notes' => [], 'chords' => [], 'steps' => [], 'stairs' => [], 'data' => ''];
        foreach ($notes as [$at, $len, $step]) {
            $out['notes'][] = [self::DO + $step, round($at * $beat, 3), round($len * $beat, 3)];
            $out['steps'][] = $step;
            $out['stairs'][] = (int) array_search(((($step % 12) + 12) % 12), self::DEGREES, true);
        }
        foreach ($chords as $i => [$at, $root, $third]) {
            $next = $chords[$i + 1][0] ?? $end;
            $bass = self::DO + $root;
            $out['chords'][] = [round($at * $beat, 3), round(($next - $at) * $beat, 3), [$bass, $bass + $third, $bass + 7]];
        }
        $out['data'] = (string) json_encode(['notes' => $out['notes'], 'chords' => $out['chords']]);

        return $out;
    }

    /**
     * The featured deck's panel (viewBox 0 0 300 176): the run's six notes, the line through them, the staff's rows.
     *
     * @return array{notes: list<array{x: float, y: float}>, line: string, rows: list<float>}
     */
    public static function speeds(): array
    {
        $y = static fn (int $midi): float => (float) ((72 - $midi) * 11 + 22);
        $notes = [];
        foreach (self::SPEEDS_RUN as $k => $midi) {
            $notes[] = ['x' => (float) (14 + $k * 46), 'y' => $y($midi) - 8];
        }
        $line = implode(' ', array_map(static fn (array $n): string => ($n['x'] + 18).','.($n['y'] + 8), $notes));

        return ['notes' => $notes, 'line' => $line, 'rows' => array_map($y, self::SPEEDS_RUN)];
    }

    /**
     * The smear deck's two panels (viewBox 0 0 240 200): the run at 8 a second in eight slots of 125 ms (the last note
     * held three), each note's segment of the pitch line: clean glides in over the first sixth of its slot and holds,
     * smeared glides across the whole slot.
     *
     * @return array{rows: list<float>, passes: array{clean: list<string>, smear: list<string>}}
     */
    public static function smear(): array
    {
        $run = self::SPEEDS_RUN;
        $y = static fn (int $midi): float => round(184 - ($midi - 60) * 13.5, 1);
        $x = static fn (float $slot): float => round(12 + $slot * 27, 1);
        $out = ['clean' => [], 'smear' => []];
        foreach ($run as $k => $midi) {
            $end = $k === count($run) - 1 ? 8 : $k + 1;
            if ($k === 0) {
                $out['clean'][] = $out['smear'][] = 'M'.$x(0).' '.$y($midi).' H'.$x(1);

                continue;
            }
            $from = 'M'.$x($k).' '.$y($run[$k - 1]);
            $out['clean'][] = $from.' L'.$x($k + 0.16).' '.$y($midi).' H'.$x($end);
            $out['smear'][] = $from.' L'.$x($k + 1).' '.$y($midi).($end > $k + 1 ? ' H'.$x($end) : '');
        }

        return ['rows' => array_map($y, $run), 'passes' => $out];
    }

    /**
     * The bend's lane (viewBox 0 0 500 220): the y of each mark (its rows are [cents above C4, label]), the path of a bend from 300 stopped at
     * `$cents` (the slider's start unless given), that stop, and its frequency in Hz to two places (the JS writes both
     * again in the page's own number format).
     *
     * @param  array<array-key, mixed>  $marks
     * @return array{marks: list<float>, path: string, cents: int, hz: string}
     */
    public static function bend(array $marks, int $cents = self::BEND_START): array
    {
        $cents = max(300, min(400, $cents));

        return [
            'marks' => array_values(array_map(static fn (mixed $mark): float => self::bendY((float) (is_array($mark) ? ($mark[0] ?? 300) : 300)), $marks)),
            'path' => 'M16 '.self::bendY(300).' H130 L250 '.self::bendY($cents).' H484',
            'cents' => $cents,
            'hz' => number_format(440 * 2 ** (($cents / 100 - 9) / 12), 2, '.', ''),
        ];
    }

    public static function bendY(int|float $cents): float
    {
        return round(196 - ($cents - 300) * 1.7, 1);
    }

    /**
     * A lesson's steps in the course file (web/course/plus/gospel-runs/course.json), or [] when it can't be read.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function lessonSteps(string $id): array
    {
        $path = rtrim((string) config('app.web_path'), '/').'/course/plus/gospel-runs/course.json';
        $file = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        foreach ((array) (is_array($file) ? ($file['units'] ?? []) : []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $id) {
                    return array_values(array_filter((array) ($lesson['steps'] ?? []), 'is_array'));
                }
            }
        }

        return [];
    }
}
