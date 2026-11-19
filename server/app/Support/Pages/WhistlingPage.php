<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Str;

/**
 * The Whistling course page's drawings and readings (site/courses/whistling, Hillel's design of 2026-11-18), kept out of
 * the view and read from the course itself (web/course/plus/whistling/course.json), so the page draws what the lessons
 * really ask: Hot Cross Buns as lesson 4 lays it in the hero, each unit's own exercise as its card's drawing, The Water
 * Is Wide's first verse beside what the app listens for, and the lessons' own tips in the page's language.
 *
 * The course lays every line from your own whistle note (a step is semitones from it), so nothing here names a letter.
 * The colours on the hero and the result card come from a SAMPLE take, written here and labelled as one on the page:
 * how many cents each note was off. Within TOLERANCE is on pitch; under it, a little flat.
 */
final class WhistlingPage
{
    /** A note within this many cents is on pitch on the sample take. */
    public const TOLERANCE = 25;

    /**
     * The hero's sample take of Hot Cross Buns, cents off per note: fifteen of seventeen on pitch, the fifth and the
     * twelfth a little flat. The still frame shows it five notes in, the sixth being whistled now.
     */
    public const SAMPLE_BUNS = [6, -9, 4, 11, -38, 3, -12, 8, 5, -4, 9, -34, 2, -7, 12, 4, -6];

    /** Where the hero's still frame stands: the note being whistled now (0-based) and the line's beat. */
    public const NOW_NOTE = 5;

    public const NOW_BEAT = 7.0;

    /** The result card's sample take of The Water Is Wide's first verse: three of its notes a little flat. */
    public const SAMPLE_VERSE_FLAT = [9, 23, 33];

    /** The sample whistle note the page plays from, as MIDI: C 6, about 1,047 Hz, where many whistles sit. */
    public const SAMPLE_BASE = 84;

    /** @var array<string, array<string, mixed>|null> */
    private static array $courses = [];

    /**
     * The course file, read once.
     *
     * @return array<string, mixed>|null
     */
    public static function course(?string $path = null): ?array
    {
        $path ??= dirname(base_path()).'/web/course/plus/whistling/course.json';
        if (! array_key_exists($path, self::$courses)) {
            $course = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$courses[$path] = is_array($course) ? $course : null;
        }

        return self::$courses[$path];
    }

    /**
     * A lesson's steps, by its id.
     *
     * @return list<array<string, mixed>>
     */
    public static function steps(string $lessonId, ?string $path = null): array
    {
        foreach ((array) (self::course($path)['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $lessonId) {
                    return array_values(array_filter((array) ($lesson['steps'] ?? []), 'is_array'));
                }
            }
        }

        return [];
    }

    /**
     * One step's notes as [beat, beats, step], step being semitones from your whistle note.
     *
     * @return list<array{0: float, 1: float, 2: int}>
     */
    public static function notes(string $lessonId, int $index, ?string $path = null): array
    {
        $step = self::steps($lessonId, $path)[$index] ?? [];
        $notes = [];
        foreach ((array) ($step['exercise']['notes'] ?? []) as $note) {
            if (is_array($note) && is_numeric($note['beat'] ?? null) && is_numeric($note['length'] ?? null) && is_numeric($note['step'] ?? null)) {
                $notes[] = [(float) $note['beat'], (float) $note['length'], (int) $note['step']];
            }
        }

        return $notes;
    }

    /** A step's tempo (beats a minute), else 72. */
    public static function tempo(string $lessonId, int $index, ?string $path = null): int
    {
        $tempo = self::steps($lessonId, $path)[$index]['exercise']['tempo'] ?? null;

        return is_numeric($tempo) ? (int) $tempo : 72;
    }

    /**
     * The hero: Hot Cross Buns as lesson 4 lays it (your note and the two just above it), each note on its row, placed
     * as a share of the tune's beats, with the sample take's cents and how the still frame shows it (on, flat, now, or
     * still to come). Empty when the course file isn't there, and the hero shows no lane.
     *
     * @return array{notes: list<array{beat: float, length: float, step: int, row: int, left: float, width: float, cents: int, state: string}>, beats: float, rows: list<int>, tempo: int, now: float, so_far: int, done: int, on: int, total: int}
     */
    public static function hero(?string $path = null): array
    {
        $notes = self::notes('wh-1-challenge', 1, $path);
        $empty = ['notes' => [], 'beats' => 0.0, 'rows' => [], 'tempo' => 72, 'now' => 0.0, 'so_far' => 0, 'done' => 0, 'on' => 0, 'total' => 0];
        if ($notes === []) {
            return $empty;
        }

        $beats = max(array_map(static fn (array $n): float => $n[0] + $n[1], $notes));
        $rows = array_values(array_unique(array_column($notes, 2)));
        rsort($rows);

        $out = [];
        foreach ($notes as $i => [$beat, $length, $step]) {
            $cents = self::SAMPLE_BUNS[$i] ?? 0;
            $out[] = [
                'beat' => $beat,
                'length' => $length,
                'step' => $step,
                'row' => (int) array_search($step, $rows, true),
                'left' => round($beat / $beats * 100, 3),
                'width' => round($length / $beats * 100, 3),
                'cents' => $cents,
                'state' => $i < self::NOW_NOTE ? self::state($cents) : ($i === self::NOW_NOTE ? 'now' : 'todo'),
            ];
        }
        $finished = array_slice($out, 0, self::NOW_NOTE);
        $on = count(array_filter($out, static fn (array $n): bool => self::state($n['cents']) === 'on'));

        return [
            'notes' => $out,
            'beats' => $beats,
            'rows' => $rows,
            'tempo' => self::tempo('wh-1-challenge', 1, $path),
            'now' => round(self::NOW_BEAT / $beats * 100, 3),
            'so_far' => count(array_filter($finished, static fn (array $n): bool => $n['state'] === 'on')),
            'done' => count($finished),
            'on' => $on,
            'total' => count($out),
        ];
    }

    /** A sample note's state by its cents: on pitch, else a little flat (or sharp). */
    public static function state(int $cents): string
    {
        return abs($cents) <= self::TOLERANCE ? 'on' : ($cents < 0 ? 'flat' : 'sharp');
    }

    /**
     * The result card: The Water Is Wide's first verse, as the last lesson lays it, each note placed by its beat and its
     * height (a share of the verse's span), the sample take's few flat notes marked.
     *
     * @return array{notes: list<array{left: float, width: float, top: float, state: string}>, on: int, total: int}
     */
    public static function verse(?string $path = null): array
    {
        $notes = self::notes('wh-4-challenge', 1, $path);
        if ($notes === []) {
            return ['notes' => [], 'on' => 0, 'total' => 0];
        }

        $beats = max(array_map(static fn (array $n): float => $n[0] + $n[1], $notes));
        $steps = array_column($notes, 2);
        [$low, $high] = [min($steps), max($steps)];
        $out = [];
        foreach ($notes as $i => [$beat, $length, $step]) {
            $out[] = [
                'left' => round($beat / $beats * 100, 3),
                'width' => round($length / $beats * 100, 3),
                'top' => round(($high - $step) / max(1, $high - $low) * 100, 3),
                'state' => in_array($i, self::SAMPLE_VERSE_FLAT, true) ? 'flat' : 'on',
            ];
        }

        return ['notes' => $out, 'on' => count($out) - count(array_intersect(self::SAMPLE_VERSE_FLAT, array_keys($out))), 'total' => count($out)];
    }

    /**
     * Each unit's drawing, from one of its real exercises, in a 300 by 70 box: bars (x, y, width, and whether it's the
     * mark of the drawing: a slide, a landed top note, the line under you), a path for a slide, and a breath's place.
     *
     * Unit 1, the held note (lesson 2); unit 2, five notes stepping up (lesson 2) and the slide up and back (lesson 3);
     * unit 3, bigger jumps, each top note landed (lesson 2); unit 4, The Water Is Wide's first phrase with the low line
     * held under it (lesson 4).
     *
     * @return list<array{bars: list<array{x: float, y: float, w: float, mark: bool}>, path: string|null, breath: float|null}>
     */
    public static function drawings(?string $path = null): array
    {
        $box = static fn (float $beats, int $low, int $high, int $top = 14, int $height = 42): array => [
            'x' => static fn (float $beat): float => round(16 + $beat / $beats * 268, 1),
            'y' => static fn (float $step): float => round($top + ($high - $step) / max(1, $high - $low) * $height, 1),
        ];
        $bar = static fn (array $b, array $n, bool $mark = false): array => ['x' => $b['x']($n[0]) + 2, 'y' => $b['y']($n[2]), 'w' => max(8.0, $b['x']($n[0] + $n[1]) - $b['x']($n[0]) - 6), 'mark' => $mark];

        // Unit 1: one note, held.
        $held = self::notes('wh-1-steady', 1, $path);
        $one = $box(4, -1, 1);
        $draw = [['bars' => array_map(static fn (array $n): array => $bar($one, $n), $held), 'path' => null, 'breath' => null]];

        // Unit 2: five notes up, then the slide up and back, after them.
        $five = array_slice(self::notes('wh-2-five', 1, $path), 0, 5);
        $slide = (array) (self::steps('wh-2-slide', $path)[1]['exercise']['target'][0]['points'] ?? []);
        $two = $box(9, -3, 3);
        $points = array_values(array_filter($slide, static fn ($p): bool => is_array($p) && is_numeric($p[0] ?? null) && is_numeric($p[1] ?? null)));
        $curve = null;
        if ($points !== []) {
            $span = max(1.0, (float) max(array_column($points, 0)));
            $at = static fn (array $p): string => $two['x'](5.4 + (float) $p[0] / $span * 3.6).' '.$two['y']((float) $p[1]);
            $curve = 'M'.$at($points[0]).implode('', array_map(static fn (array $p): string => ' L'.$at($p), array_slice($points, 1)));
        }
        $draw[] = ['bars' => array_map(static fn (array $n): array => $bar($two, $n), $five), 'path' => $curve, 'breath' => null];

        // Unit 3: jumps up from a low note, each top note landed.
        $jumps = self::notes('wh-3-bigger', 3, $path);
        $low = $jumps === [] ? 0 : min(array_column($jumps, 2));
        $three = $box(max(1.0, ...array_map(static fn (array $n): float => $n[0] + $n[1], $jumps ?: [[0, 1, 0]])), $low, $jumps === [] ? 1 : max(array_column($jumps, 2)));
        $draw[] = ['bars' => array_map(static fn (array $n): array => $bar($three, $n, $n[2] > $low), $jumps), 'path' => null, 'breath' => null];

        // Unit 4: the tune's first phrase (to its first breath), over the low line held under it.
        $tune = self::notes('wh-4-challenge', 1, $path);
        $under = self::notes('wh-4-challenge', 2, $path);
        $phrase = array_values(array_filter($tune, static fn (array $n): bool => $n[0] < 12));
        $line = array_values(array_filter($under, static fn (array $n): bool => $n[0] < 12));
        $steps = array_merge(array_column($phrase, 2), array_column($line, 2), [0]);
        $four = $box(13, min($steps), max($steps), 12, 34);
        $breath = null;
        $last = end($phrase);
        if ($last !== false && $last[0] + $last[1] < 12) {
            $breath = $four['x']($last[0] + $last[1] + 0.6);
        }
        $draw[] = ['bars' => [...array_map(static fn (array $n): array => $bar($four, $n), $phrase), ...array_map(static fn (array $n): array => ['x' => $four['x']($n[0]) + 2, 'y' => 60.0, 'w' => max(8.0, $four['x'](min(12.0, $n[0] + $n[1])) - $four['x']($n[0]) - 4), 'mark' => true], $line)], 'path' => null, 'breath' => $breath];

        return $draw;
    }

    /**
     * A lesson's first card (its learn step) in the page's language (else English): the headline and its points as
     * [title, detail], those that say the safety line left out (the page says it once, in its own words).
     *
     * @return array{headline: string, points: list<array{0: string, 1: string}>}
     */
    public static function card(string $lessonId, ?string $path = null): array
    {
        $say = static function (mixed $words): string {
            $words = (array) $words;

            return (string) ($words[app()->getLocale()] ?? $words['en'] ?? '');
        };
        foreach (self::steps($lessonId, $path) as $step) {
            if (($step['kind'] ?? null) !== 'learn') {
                continue;
            }
            $points = [];
            foreach ((array) ($step['steps'] ?? []) as $point) {
                if (! is_array($point) || str_contains((string) ($point['detail']['en'] ?? ''), 'light-headed')) {
                    continue;
                }
                $points[] = [$say($point['title'] ?? []), $say($point['detail'] ?? [])];
            }

            return ['headline' => $say($step['headline'] ?? []), 'points' => $points];
        }

        return ['headline' => '', 'points' => []];
    }

    /**
     * The last lesson's two whistles, each on its own track (a share of the verse's beats across, of its own span down):
     * The Water Is Wide's first verse, then the low line you whistle under your own kept tune.
     *
     * @return array{tune: list<array{left: float, width: float, top: float}>, under: list<array{left: float, width: float, top: float}>}
     */
    public static function finale(?string $path = null): array
    {
        $tune = self::notes('wh-4-challenge', 1, $path);
        $under = self::notes('wh-4-challenge', 2, $path);
        if ($tune === [] || $under === []) {
            return ['tune' => [], 'under' => []];
        }
        $beats = max(array_map(static fn (array $n): float => $n[0] + $n[1], $tune));
        /** @param list<array{0: float, 1: float, 2: int}> $notes */
        $place = static function (array $notes) use ($beats): array {
            [$low, $high] = [PHP_INT_MAX, PHP_INT_MIN];
            foreach ($notes as $note) {
                [$low, $high] = [min($low, $note[2]), max($high, $note[2])];
            }
            $out = [];
            foreach ($notes as [$beat, $length, $step]) {
                $out[] = [
                    'left' => round($beat / $beats * 100, 3),
                    'width' => round(min($length, $beats - $beat) / $beats * 100, 3),
                    'top' => $high === $low ? 50.0 : round(($high - $step) / ($high - $low) * 100, 3),
                ];
            }

            return $out;
        };

        return ['tune' => $place($tune), 'under' => $place($under)];
    }

    /** A step's own words (its `text`) in the page's language, else English. */
    public static function stepText(string $lessonId, int $index, ?string $path = null): string
    {
        $text = (array) (self::steps($lessonId, $path)[$index]['text'] ?? []);

        return (string) ($text[app()->getLocale()] ?? $text['en'] ?? '');
    }

    /** One tip from each unit: a lesson's first card and which of its points. */
    public const TIPS = [['wh-1-start', 0], ['wh-2-step', 0], ['wh-3-small', 0], ['wh-4-breath', 0]];

    /**
     * One tip from each unit's lessons, as [title, detail] in the page's language, in unit order.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function tips(?string $path = null): array
    {
        $tips = [];
        foreach (self::TIPS as [$lesson, $point]) {
            $tip = self::card($lesson, $path)['points'][$point] ?? null;
            if ($tip !== null) {
                $tips[] = $tip;
            }
        }

        return $tips;
    }

    /**
     * The songs in the order you meet them, each with its unit (1-based): a song's unit is the one with a lesson of its
     * name (each unit ends on its tune). A song no lesson names goes last, with no unit.
     *
     * @param  array<array-key, mixed>  $songs  [title, credit] pairs
     * @param  array{units?: array<array-key, mixed>}|null  $outline
     * @return list<array{title: string, credit: string, unit: int|null}>
     */
    public static function songsByUnit(array $songs, ?array $outline): array
    {
        $units = [];
        foreach ((array) ($outline['units'] ?? []) as $u => $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_string($lesson)) {
                    $units[mb_strtolower($lesson)] ??= (int) $u + 1;
                }
            }
        }
        $out = [];
        foreach ($songs as $song) {
            if (is_array($song) && is_string($song[0] ?? null) && is_string($song[1] ?? null)) {
                $out[] = ['title' => $song[0], 'credit' => $song[1], 'unit' => $units[mb_strtolower($song[0])] ?? null];
            }
        }
        usort($out, static fn (array $a, array $b): int => ($a['unit'] ?? PHP_INT_MAX) <=> ($b['unit'] ?? PHP_INT_MAX));

        return $out;
    }

    /**
     * The questions with the course's own numbers put in (:units, :lessons, :minutes).
     *
     * @param  array<array-key, mixed>  $faq
     * @param  array{units?: array<array-key, mixed>, lessons?: int, minutes?: int}|null  $outline
     * @return list<array{0: string, 1: string}>
     */
    public static function faq(array $faq, ?array $outline): array
    {
        $numbers = [':units' => (string) count((array) ($outline['units'] ?? [])), ':lessons' => (string) ($outline['lessons'] ?? 0), ':minutes' => (string) ($outline['minutes'] ?? 0)];
        $out = [];
        foreach ($faq as $qa) {
            if (is_array($qa) && is_string($qa[0] ?? null) && is_string($qa[1] ?? null)) {
                $out[] = [strtr($qa[0], $numbers), strtr($qa[1], $numbers)];
            }
        }

        return $out;
    }

    /**
     * Each unit's lesson pages' addresses, in order (1-based units), once the course has lesson pages; none before.
     *
     * @return array<int, list<string>>
     */
    public static function lessonLinks(string $course = 'whistling'): array
    {
        $links = [];
        foreach (Lessons::pages($course) as $page) {
            if (($page['kind'] ?? null) === 'lesson' && is_string($page['url'] ?? null) && is_numeric($page['unitNumber'] ?? null)) {
                $links[(int) $page['unitNumber']][] = $page['url'];
            }
        }

        return $links;
    }

    /** The hero's lesson in the course (unit 1, lesson 4: Hot Cross Buns). */
    public const HERO_UNIT = 1;

    public const HERO_LESSON = 4;

    /** The hero's place in the course in words ("Unit 1 · lesson 4"). */
    public static function heroAt(string $words): string
    {
        return strtr($words, [':unit' => (string) self::HERO_UNIT, ':lesson' => (string) self::HERO_LESSON]);
    }

    /**
     * The hero's notes for its player: [beat, beats, step, cents] each.
     *
     * @param  array{notes: list<array{beat: float, length: float, step: int, cents: int}>}  $hero
     */
    public static function notesJson(array $hero): string
    {
        return (string) json_encode(array_map(static fn (array $n): array => [$n['beat'], $n['length'], $n['step'], $n['cents']], $hero['notes']));
    }

    /** A count in words (":n of :total notes on pitch"). */
    public static function fill(string $words, int $n, int $total): string
    {
        return strtr($words, [':n' => (string) $n, ':total' => (string) $total]);
    }

    /** A unit's line from the page's `learn` list ("The tongue: the next note up ..."), without its unit's name. */
    public static function unitLine(mixed $learn): string
    {
        return is_string($learn) && $learn !== '' ? Str::ucfirst(Str::after($learn, ': ')) : '';
    }

    /** Two digits, as the lessons are numbered (1 is 01). */
    public static function two(int $n): string
    {
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
