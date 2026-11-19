<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Str;

/**
 * What Blues singing's own page (site/courses/blues-singing, Hillel's design of 2026-11-18) draws, so its view stays
 * markup: Shorty George Blues' twelve bars as the course teaches them, the blue-note demo's first line, lesson 1's steps
 * from the course, the sample take, and its words cut where the design sets a part apart (the last sentence in italics,
 * a unit's name over its line, a song's year).
 */
final class BluesSingingPage
{
    /** The verse's three lines: say it, say it again, answer it. */
    public const LETTERS = ['A', 'A', 'B'];

    /**
     * Shorty George Blues' first verse as the template has it (database/templates/shorty-george-blues.json, the 1924
     * printing's chords a whole tone up, the same numbers): bars 1 to 3 I, bar 4 I7; 5 and 6 IV7, 7 I, 8 I then V7; 9 V7,
     * 10 I then V7, 11 I7, 12 I then V7, the course's own exercises (bl-1-challenge, bl-4-challenge) the same in C. So
     * bar 10 is I, not the textbook's IV. Each line is sung over its first three bars (the pickup aside) and the band has
     * the fourth: bar 12 holds only a beat and a half of the answer line's end.
     *
     * @var list<array{0: list<string>, 1: bool}>
     */
    private const BARS = [
        [['I'], true], [['I'], true], [['I'], true], [['I'], false],
        [['IV'], true], [['IV'], true], [['I'], true], [['I', 'V'], false],
        [['V'], true], [['I', 'V'], true], [['I'], true], [['I', 'V'], false],
    ];

    /**
     * The demo's line: Shorty George Blues' first line as lesson 1 sings it (course/plus/blues-singing, bl-1-say's last
     * step), each note [beat, beats, steps above the key note, its word or '' where the word before holds on]. The blue
     * notes are its steps of 3, each leaning just under the bright note (4) and stepping up onto it.
     *
     * @var list<array{0: int|float, 1: int|float, 2: int, 3: string}>
     */
    private const LINE = [
        [0, 1, 7, 'I'], [1, 1, 7, 'wrote'], [2, 0.5, 3, 'a'], [2.5, 0.5, 4, 'let-'], [3, 1, 0, 'ter'],
        [4, 0.5, 3, 'and'], [4.5, 0.5, 4, 'I'], [5, 1, 0, 'mailed'], [6, 0.5, 3, 'it'], [6.5, 0.5, 4, 'right'],
        [7, 1, 0, 'in'], [8, 0.5, 3, 'the'], [8.5, 0.5, 4, ''], [9, 4, 0, 'air'],
    ];

    /** The blue note's step, and the bright note's a step above it, put in its place in the demo's "without" version. */
    public const BLUE = 3;

    public const BRIGHT = 4;

    /** The line's bar: four bars of four beats, the last (from beat 13, the word "air" done) the band's turn. */
    public const BEATS = 16;

    public const TURN = 13;

    /** The lesson whose steps the page sets out as the first lesson. */
    public const FIRST_LESSON = 'bl-1-say';

    /** The sample take's missed notes, of three lines of eight: the design's 21 of 24, the scoring line's example. */
    private const MISSED = [5, 14, 22];

    /**
     * The chart's rows, each its letter, its name and its four bars, every bar named for a screen reader.
     *
     * @param  array<string, mixed>  $chart  the page's love.chart words
     * @return list<array{letter: string, name: string, bars: list<array{n: int, chords: list<string>, sung: bool, label: string}>}>
     */
    public static function rows(array $chart): array
    {
        $rows = [];
        foreach (array_chunk(self::BARS, 4) as $row => $bars) {
            $rows[] = [
                'letter' => self::LETTERS[$row],
                'name' => (string) (((array) ($chart['rows'] ?? []))[$row] ?? ''),
                'bars' => array_map(static function (array $bar, int $i) use ($row, $chart): array {
                    [$chords, $sung] = $bar;
                    $n = $row * 4 + $i + 1;

                    return ['n' => $n, 'chords' => $chords, 'sung' => $sung, 'label' => strtr((string) ($chart['bar'] ?? ''), [
                        ':n' => (string) $n,
                        ':chords' => implode(' ', $chords),
                        ':part' => (string) ($sung ? ($chart['sung'] ?? '') : ($chart['turn'] ?? '')),
                    ])];
                }, $bars, array_keys($bars)),
            ];
        }

        return $rows;
    }

    /**
     * The sample take: each line's letter and its eight notes, true where on pitch.
     *
     * @return list<array{letter: string, notes: list<bool>}>
     */
    public static function take(): array
    {
        return array_map(static fn (int $row): array => [
            'letter' => self::LETTERS[$row],
            'notes' => array_map(static fn (int $i): bool => ! in_array($row * 8 + $i, self::MISSED, true), range(0, 7)),
        ], array_keys(self::LETTERS));
    }

    /** @return array{hit: int, all: int} */
    public static function takeCount(): array
    {
        return ['hit' => count(self::LETTERS) * 8 - count(self::MISSED), 'all' => count(self::LETTERS) * 8];
    }

    /**
     * What the app listens for, as the design sets it: the first sentence, then the rest with its last sentence apart.
     *
     * @return array{first: string, middle: string, last: string}
     */
    public static function measures(string $text): array
    {
        $sentences = preg_split('/(?<=\.)\s+/u', trim($text)) ?: [];
        if (count($sentences) < 3) {
            return ['first' => $sentences[0] ?? '', 'middle' => implode(' ', array_slice($sentences, 1)), 'last' => ''];
        }

        return ['first' => $sentences[0], 'middle' => implode(' ', array_slice($sentences, 1, -1)), 'last' => $sentences[count($sentences) - 1]];
    }

    /**
     * A sentence with its ending set apart, when it ends with it.
     *
     * @return array{before: string, after: string}
     */
    public static function ending(string $text, string $ending): array
    {
        return $ending !== '' && str_ends_with($text, $ending)
            ? ['before' => rtrim(mb_substr($text, 0, mb_strlen($text) - mb_strlen($ending))), 'after' => $ending]
            : ['before' => $text, 'after' => ''];
    }

    /**
     * What you'll learn, each unit's name (before its colon) over its line.
     *
     * @param  array<int, mixed>  $learn
     * @return list<array{name: string, text: string}>
     */
    public static function aims(array $learn): array
    {
        return array_values(array_map(static function (mixed $point): array {
            [$name, $text] = array_pad(explode(': ', (string) $point, 2), 2, '');

            return $text === '' ? ['name' => '', 'text' => $name.'.'] : ['name' => $name, 'text' => Str::ucfirst($text).'.'];
        }, $learn));
    }

    /**
     * The demo's roll (viewBox 0 0 600 180): each note's place and size, at its printed step and, for a blue note, at the
     * bright note's step too (the version without it); the word it sings under (a held note keeps the word before); the
     * band's turn's place; the lines the steps sit on; and the line as the sound wants it, [beat, beats, step].
     *
     * @return array{notes: list<array{x: float, w: float, y: float, plain: float, blue: bool, word: int}>, words: list<string>, turn: array{x: float, w: float}, guides: list<float>, line: list<array{0: int|float, 1: int|float, 2: int}>}
     */
    public static function demo(): array
    {
        $x = static fn (float $beat): float => round(16 + $beat / self::BEATS * 568, 1);
        $y = static fn (int $step): float => (float) (146 - $step * 15);
        $notes = [];
        $words = [];
        foreach (self::LINE as [$beat, $beats, $step, $word]) {
            if ($word !== '') {
                $words[] = $word;
            }
            $notes[] = [
                'x' => $x($beat),
                'w' => round($x($beat + $beats) - $x($beat) - 4, 1),
                'y' => $y($step) - 6,
                'plain' => $y($step === self::BLUE ? self::BRIGHT : $step) - 6,
                'blue' => $step === self::BLUE,
                'word' => max(0, count($words) - 1),
            ];
        }

        return [
            'notes' => $notes,
            'words' => $words,
            'turn' => ['x' => $x(self::TURN), 'w' => round($x(self::BEATS) - $x(self::TURN) - 4, 1)],
            'guides' => array_map($y, [0, self::BLUE, self::BRIGHT, 7]),
            'line' => array_map(static fn (array $note): array => [$note[0], $note[1], $note[2]], self::LINE),
        ];
    }

    /**
     * Lesson 1's steps as the course file has them (web/course/plus/blues-singing/course.json), in the page's language:
     * each step's kind, its words (a learn card's headline, else its line) and what it's scored on, where it says.
     *
     * @return list<array{kind: string, text: string, measures: string}>
     */
    public static function firstLesson(): array
    {
        $locale = app()->getLocale();
        $said = static fn (mixed $words): string => is_array($words) ? (string) ($words[$locale] ?? $words['en'] ?? '') : '';
        $steps = [];
        foreach (self::lessonSteps(self::FIRST_LESSON) as $step) {
            $kind = (string) ($step['kind'] ?? '');
            $steps[] = [
                'kind' => $kind,
                'text' => $said($kind === 'learn' ? ($step['headline'] ?? null) : ($step['text'] ?? null)),
                'measures' => $said($step['measures'] ?? null),
            ];
        }

        return $steps;
    }

    /**
     * A lesson's steps in the course file, or [] when it can't be read.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function lessonSteps(string $id): array
    {
        $path = rtrim((string) config('app.web_path'), '/').'/course/plus/blues-singing/course.json';
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

    /** A song's credit's year, "(Chicago: ..., 1924)", or nothing. */
    public static function year(string $credit): string
    {
        return preg_match('/(\d{4})\)/', $credit, $year) === 1 ? $year[1] : '';
    }

    /**
     * The cover's title, its last word apart ("Shorty George" over "Blues").
     *
     * @return array{lines: list<string>, last: string}
     */
    public static function title(string $title): array
    {
        $words = explode(' ', trim($title));
        $last = count($words) > 1 ? (string) array_pop($words) : '';

        return ['lines' => $words, 'last' => $last];
    }
}
