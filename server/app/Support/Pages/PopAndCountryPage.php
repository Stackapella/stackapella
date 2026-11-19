<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * Pop and country styling's own course page (site/courses/pop-and-country, Hillel's design, 2026-11-18: a vinyl double
 * album): what its view shows that it reads from elsewhere or works out, so the view stays markup. Lesson 1's line (the
 * course file's own notes and chords, which js/public/pop-and-country.js sings and draws on the same scale), the
 * tracklist (each unit a side, A to D, its lessons tracks A1 to A4, each linked to its lesson page), the four moves'
 * small drawings, and the sample take's drawing on the same notes.
 */
final class PopAndCountryPage
{
    /** The roll's drawing box (the view's SVG viewBox and the JS's): x is time, y the pitch. */
    public const WIDTH = 1000;

    public const HEIGHT = 200;

    /** Pixels a semitone on the roll, and the y of the comfortable note (step 0). */
    public const STEP = 11;

    public const MIDDLE = 120;

    /** Seconds after the last note's end that the line takes to fade, as the JS sings it. */
    public const TAIL = 0.4;

    /** The four moves, in the page's order (lang `moves.kinds`), and each one's small drawing (a 200 by 60 box). */
    public const MOVES = [
        'scoop' => 'M8 50 C40 50 46 18 78 18 L192 18',
        'fall' => 'M8 18 L124 18 C156 18 170 34 192 54',
        'slide' => 'M8 48 L78 48 C110 48 116 16 148 16 L192 16',
        'cry' => 'M8 42 L88 42 L100 10 L112 42 L192 42',
    ];

    /** Side B's moves picked to start with: the two the first unit teaches. */
    public const CHOSEN = ['scoop', 'fall'];

    /**
     * The four moves as the page shows them: each one's kind (what the JS plays), its name and line (the page's
     * `moves.kinds`, in order) and its drawing.
     *
     * @param  array<array-key, mixed>  $kinds
     * @return list<array{kind: string, name: string, what: string, path: string}>
     */
    public static function moves(array $kinds): array
    {
        $moves = [];
        foreach (array_keys(self::MOVES) as $i => $kind) {
            $words = $kinds[$i] ?? null;
            if (is_array($words) && is_string($words[0] ?? null) && is_string($words[1] ?? null)) {
                $moves[] = ['kind' => $kind, 'name' => $words[0], 'what' => $words[1], 'path' => self::MOVES[$kind]];
            }
        }

        return $moves;
    }

    /**
     * Lesson 1's line as the guide sings it (pop-1-style's `listen`): its tempo, its notes as `[beat, length, step,
     * lyric]` (step in semitones from the comfortable note) and its chords as `[beat, root, quality]`. Empty notes when
     * the course file or the step isn't there, and the page shows no roll.
     *
     * @return array{tempo: int, notes: list<array{0: float|int, 1: float|int, 2: int, 3: string}>, chords: list<array{0: float|int, 1: int, 2: string}>}
     */
    public static function line(?string $path = null): array
    {
        $path ??= dirname(base_path()).'/web/course/plus/pop/course.json';
        $course = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $steps = is_array($course) ? (array) ($course['units'][0]['lessons'][0]['steps'] ?? []) : [];

        $listen = null;
        foreach ($steps as $step) {
            if (is_array($step) && is_array($step['listen']['notes'] ?? null)) {
                $listen = $step['listen'];
                break;
            }
        }

        if ($listen === null) {
            return ['tempo' => 96, 'notes' => [], 'chords' => []];
        }

        $notes = [];
        foreach ((array) $listen['notes'] as $note) {
            if (is_array($note) && is_numeric($note['beat'] ?? null) && is_numeric($note['length'] ?? null) && is_numeric($note['step'] ?? null)) {
                $notes[] = [$note['beat'] + 0, $note['length'] + 0, (int) $note['step'], is_string($note['lyric'] ?? null) ? $note['lyric'] : ''];
            }
        }

        // Only the chords that sound under the line.
        $end = $notes === [] ? 0 : max(array_map(static fn (array $n): float|int => $n[0] + $n[1], $notes));
        $chords = [];
        foreach ((array) ($listen['chords'] ?? []) as $chord) {
            if (is_array($chord) && is_numeric($chord['beat'] ?? null) && is_numeric($chord['root'] ?? null) && $chord['beat'] < $end) {
                $chords[] = [$chord['beat'] + 0, (int) $chord['root'], is_string($chord['quality'] ?? null) ? $chord['quality'] : 'maj'];
            }
        }

        return ['tempo' => is_numeric($listen['tempo'] ?? null) ? (int) $listen['tempo'] : 96, 'notes' => $notes, 'chords' => $chords];
    }

    /**
     * The roll's still drawing of the line: each note a bar at its time and pitch (`x`, `y`, `w` in the roll's box), and
     * each word's place along it (`left`, a percentage, and its `row`, 0 or 1), on the scale the JS draws the trace and moves the playhead on.
     *
     * @param  array{tempo: int, notes: list<array{0: float|int, 1: float|int, 2: int, 3: string}>}  $line
     * @return list<array{x: float, y: float, w: float, left: float, row: int, word: string}>
     */
    public static function roll(array $line): array
    {
        if ($line['notes'] === []) {
            return [];
        }
        $beat = 60 / max(1, $line['tempo']);
        $last = $line['notes'][count($line['notes']) - 1];
        $total = ($last[0] + $last[1]) * $beat + self::TAIL;
        $bars = [];
        $before = null;
        foreach ($line['notes'] as [$at, $long, $step, $word]) {
            $x = $at * $beat / $total * self::WIDTH;
            // A word less than two beats after the one before it on its row takes the other row, so short notes' words
            // never run into each other on a narrow roll.
            $row = $before !== null && $at - $before['at'] < 2 ? 1 - $before['row'] : 0;
            $bars[] = [
                'x' => round($x + 2, 1),
                'y' => (float) (self::MIDDLE - $step * self::STEP - 5),
                'w' => round($long * $beat / $total * self::WIDTH - 6, 1),
                'left' => round($x / self::WIDTH * 100, 2),
                'row' => $row,
                'word' => $word,
            ];
            $before = ['at' => $at, 'row' => $row];
        }

        return $bars;
    }

    /**
     * The line's data for js/public/pop-and-country.js (one source: the course file), with the roll's scale.
     *
     * @param  array{tempo: int, notes: list<array{0: float|int, 1: float|int, 2: int, 3: string}>, chords: list<array{0: float|int, 1: int, 2: string}>}  $line
     */
    public static function json(array $line): string
    {
        return (string) json_encode(['tempo' => $line['tempo'], 'notes' => $line['notes'], 'chords' => $line['chords'], 'width' => self::WIDTH, 'step' => self::STEP, 'middle' => self::MIDDLE, 'tail' => self::TAIL], JSON_UNESCAPED_UNICODE);
    }

    /**
     * The tracklist: each unit a side (A, B, C, D...) with its lessons as tracks (A1, A2...), each linked to the lesson
     * page it's on (a unit's challenge is on its last lesson's page), the first one marked to start there.
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>}  $outline
     * @return list<array{side: string, n: int, title: string, tracks: list<array{code: string, name: string, url: string|null, first: bool}>}>
     */
    public static function sides(array $outline, string $course = 'pop-and-country', ?string $path = null): array
    {
        $path ??= dirname(base_path()).'/web/course/plus/pop/course.json';
        $file = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $pages = [];
        foreach (Lessons::pages($course) as $page) {
            foreach ((array) ($page['ids'] ?? []) as $id) {
                $pages[(string) $id] ??= (string) $page['url'];
            }
        }

        $sides = [];
        foreach ($outline['units'] as $u => $unit) {
            $side = self::side($u + 1);
            $lessons = is_array($file) ? array_values(array_filter((array) ($file['units'][$u]['lessons'] ?? []), 'is_array')) : [];
            $tracks = [];
            foreach ($unit['lessons'] as $l => $name) {
                $id = (string) ($lessons[$l]['id'] ?? '');
                $tracks[] = ['code' => $side.($l + 1), 'name' => $name, 'url' => $pages[$id] ?? null, 'first' => $u === 0 && $l === 0];
            }
            $sides[] = ['side' => $side, 'n' => $u + 1, 'title' => $unit['title'], 'tracks' => $tracks];
        }

        return $sides;
    }

    /**
     * Words set on two lines (the sticker's motto): split at the first space, or one line if there's none.
     *
     * @return list<string>
     */
    public static function twoLines(string $words): array
    {
        $words = trim($words);
        $cut = strpos($words, ' ');

        return $cut === false ? [$words] : [substr($words, 0, $cut), substr($words, $cut + 1)];
    }

    /** A side's letter: 1 is A. */
    public static function side(int $n): string
    {
        return chr(ord('A') + max(0, $n - 1));
    }

    /**
     * The sample take's drawing (the card plainly marked as a sample): the line's bars and a voice line over them on the
     * same notes, with a scoop into the first long note and a fall-off at the next, heard, and the slide asked for after
     * the breath not made yet. `dots` are where each move is marked: [x, y, heard].
     *
     * @param  array{tempo: int, notes: list<array{0: float|int, 1: float|int, 2: int, 3: string}>}  $line
     * @return array{bars: list<string>, trace: string, dots: list<array{0: float, 1: float, 2: bool}>}
     */
    public static function sample(array $line): array
    {
        $notes = $line['notes'];
        if (count($notes) < 7) {
            return ['bars' => [], 'trace' => '', 'dots' => []];
        }
        $last = $notes[count($notes) - 1];
        $span = $last[0] + $last[1];
        $x = static fn (float|int $beat): float => round($beat / $span * 400, 1);
        $y = static fn (int $step): float => (float) (70 - $step * 6);

        $bars = [];
        foreach ($notes as [$at, $long, $step]) {
            $bars[] = 'M'.$x($at + 0.1).' '.$y($step).' H'.$x($at + $long - 0.1);
        }

        // The voice: straight but for the scoop into note 3 ("val-") and the fall-off at the end of note 4 ("ley,").
        $d = 'M'.$x($notes[0][0]).' '.$y($notes[0][2]);
        foreach ($notes as $i => [$at, $long, $step]) {
            $start = $x($at);
            $end = $x($at + $long);
            if ($i === 3) {
                $d .= ' L'.$start.' '.($y($step) + 14).' C'.($start + 6).' '.($y($step) + 14).' '.($start + 8).' '.$y($step).' '.($start + 16).' '.$y($step);
            } else {
                $d .= ' L'.$start.' '.$y($step);
            }
            if ($i === 4) {
                $d .= ' L'.($end - 14).' '.$y($step).' C'.($end - 6).' '.$y($step).' '.($end - 2).' '.($y($step) + 8).' '.$end.' '.($y($step) + 22).' M'.$x($notes[5][0]).' '.$y($notes[5][2]);
            } else {
                $d .= ' L'.$end.' '.$y($step);
            }
        }

        return [
            'bars' => $bars,
            'trace' => $d,
            'dots' => [
                [$x($notes[3][0]) + 4, $y($notes[3][2]) + 8, true],
                [$x($notes[4][0] + $notes[4][1]) - 4, $y($notes[4][2]) + 10, true],
                [$x($notes[5][0] + $notes[5][1]), ($y($notes[5][2]) + $y($notes[6][2])) / 2, false],
            ],
        ];
    }
}
