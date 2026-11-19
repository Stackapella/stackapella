<?php

declare(strict_types=1);

namespace App\Support\Pages;

use NumberFormatter;

/**
 * What the Range and registers course page (site/courses/range-and-registers) works out beyond its words: the hero's
 * 63-key ruler from the basses' B flat 1 (MIDI 34) to Agujari's C7 (MIDI 96), its marked notes and C labels; each
 * section label's note and its hertz at A4 = 440 (rising down the page, like singing up a scale); the demo's scale
 * rows; the archive's timeline and plate numbers; and the climb, each unit's lessons as links to their pages
 * (Lessons::pages). The words are lang/en/site.php courses.pages.range-and-registers.
 */
final class RangeAndRegistersPage
{
    /** The ruler's ends: the basses' B flat 1 and Agujari's C7. */
    public const LOW = 34;

    public const HIGH = 96;

    /** The ruler's break zone, D4 to A4: drawn hatched and labelled as varying by voice (no source gives one place). */
    public const BREAK_LOW = 62;

    public const BREAK_HIGH = 69;

    /** The demo's scale: C3 to C5 on the white keys (15 rows), up and back down; the band starts at C4 (row 7). */
    public const ROWS = 15;

    public const BAND = 7;

    /** Each section label's note, rising down the page from D2 to F6; the break band comes between F3 and A4. */
    public const NOTES = ['demo' => 38, 'outcomes' => 43, 'plain' => 48, 'archive' => 53, 'ends' => 69, 'climb' => 72, 'first' => 76, 'notes' => 81, 'kit' => 84, 'faq' => 89];

    /** The hero ruler's marked notes: the basses' B flat 1, Osmin's D2, middle C, the Queen of the Night's F6, Agujari's C7. */
    public const MARKS = [34 => 'low', 38 => 'low', 60 => 'mid', 89 => 'high', 96 => 'high'];

    private const SLUG = 'range-and-registers';

    /** The twelve note names from C, as the page's `letters` gives them (Do Re Mi in Spanish). */
    private const LETTERS = 'C C♯ D E♭ E F F♯ G A♭ A B♭ B';

    private const WHITE = [0, 2, 4, 5, 7, 9, 11];

    /** A note's name, C4 for middle C (B♭1, D2, F6, C7), its letter from `$letters` (twelve names from C, spaced). */
    public static function name(int $midi, string $letters = self::LETTERS): string
    {
        $names = preg_split('/\s+/u', trim($letters)) ?: [];
        if (count($names) !== 12) {
            $names = explode(' ', self::LETTERS);
        }

        return $names[$midi % 12].(intdiv($midi, 12) - 1);
    }

    /** A note's frequency at A4 = 440 Hz, equal temperament. */
    public static function frequency(int $midi): float
    {
        return 440 * 2 ** (($midi - 69) / 12);
    }

    /** A note's hertz to the nearest whole number, written as the page's language writes it (2,093 in English). */
    public static function hz(int $midi): string
    {
        $value = (int) round(self::frequency($midi));
        if (! class_exists(NumberFormatter::class)) {
            return number_format($value);
        }
        $format = new NumberFormatter(app()->getLocale(), NumberFormatter::DECIMAL);
        $format->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);

        return (string) $format->format($value);
    }

    /** A note in the page's words: `:note` its name and `:hz` its hertz ("D2 · 73 Hz"). */
    public static function label(string $words, int $midi, string $letters = self::LETTERS): string
    {
        return str_replace([':note', ':hz'], [self::name($midi, $letters), self::hz($midi)], $words);
    }

    /**
     * Each section's label note: its words ("D2 · 73 Hz") and its side of the break ('chest' below it, 'head' above).
     *
     * @return array<string, array{text: string, side: string}>
     */
    public static function markers(string $words, string $letters = self::LETTERS): array
    {
        $markers = [];
        foreach (self::NOTES as $section => $midi) {
            $markers[$section] = ['text' => self::label($words, $midi, $letters), 'side' => $midi < self::BREAK_LOW ? 'chest' : 'head'];
        }

        return $markers;
    }

    /** Where a key's centre sits along the ruler, in percent. */
    public static function at(int $midi): float
    {
        return round(($midi - self::LOW + 0.5) / (self::HIGH - self::LOW + 1) * 100, 3);
    }

    /**
     * The ruler's 63 keys, low to high: each a C, another white key or a black key, and below, in or above the break zone.
     *
     * @return list<array{kind: string, zone: string}>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (range(self::LOW, self::HIGH) as $midi) {
            $keys[] = [
                'kind' => $midi % 12 === 0 ? 'c' : (in_array($midi % 12, self::WHITE, true) ? 'white' : 'black'),
                'zone' => $midi < self::BREAK_LOW ? 'low' : ($midi > self::BREAK_HIGH ? 'high' : 'break'),
            ];
        }

        return $keys;
    }

    /**
     * The C's under the ruler, C2 to C7: each name and where it sits.
     *
     * @return list<array{name: string, at: float}>
     */
    public static function octaves(string $letters = self::LETTERS): array
    {
        return array_map(static fn (int $midi): array => ['name' => self::name($midi, $letters), 'at' => self::at($midi)], range(36, self::HIGH, 12));
    }

    /**
     * The hero ruler's marked notes: each one's kind (low, mid or high), where it sits, and whether its stem is short
     * (Osmin's D2 and the Queen of the Night's F6, inside the ends they sit beside).
     *
     * @return list<array{kind: string, at: float, short: bool}>
     */
    public static function marks(): array
    {
        $marks = [];
        foreach (self::MARKS as $midi => $kind) {
            $marks[] = ['kind' => $kind, 'at' => self::at($midi), 'short' => in_array($midi, [38, 89], true)];
        }

        return $marks;
    }

    /**
     * The break zone's start and width along the ruler, in percent.
     *
     * @return array{from: float, width: float}
     */
    public static function breakZone(): array
    {
        $from = (self::BREAK_LOW - self::LOW) / (self::HIGH - self::LOW + 1) * 100;
        $to = (self::BREAK_HIGH - self::LOW + 1) / (self::HIGH - self::LOW + 1) * 100;

        return ['from' => round($from, 3), 'width' => round($to - $from, 3)];
    }

    /**
     * Words with marked parts (`{low}Low{/low} to {high}high{/high}`: no colon, so a translation's placeholders still
     * match), split so the view prints each part escaped and wraps the marked ones: a list of [text, mark or null].
     *
     * @return list<array{0: string, 1: string|null}>
     */
    public static function parts(string $words): array
    {
        $pieces = preg_split('/\{([a-z]+)\}(.*?)\{\/\1\}/u', $words, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$words];
        $parts = [];
        for ($i = 0; $i < count($pieces); $i += 3) {
            if ($pieces[$i] !== '') {
                $parts[] = [$pieces[$i], null];
            }
            if (isset($pieces[$i + 2])) {
                $parts[] = [$pieces[$i + 2], $pieces[$i + 1]];
            }
        }

        return $parts;
    }

    /** The demo's row names, C3 to C5 on the white keys. */
    public static function row(int $row, string $letters = self::LETTERS): string
    {
        return self::name(48 + 12 * intdiv($row, 7) + self::WHITE[$row % 7], $letters);
    }

    /** The demo band's words for the band at rows `$low` to `$low + 3` (":low to :high"). */
    public static function band(string $words, string $letters = self::LETTERS, int $low = self::BAND): string
    {
        return str_replace([':low', ':high'], [self::row($low, $letters), self::row($low + 3, $letters)], $words);
    }

    /** A row's height in the demo's 600 by 240 stage. */
    public static function rowY(int $row): float
    {
        return 216 - $row * 13.5;
    }

    /**
     * The demo's 29 steps, up from C3 to C5 and back down: each one's row, where it's drawn, and whether the default
     * band (C4 to F4) puts it in head voice (up, chest to the band's top; down, head to its bottom, as the script).
     *
     * @return list<array{row: int, x: float, y: float, w: float, head: bool}>
     */
    public static function steps(): array
    {
        $path = array_merge(range(0, self::ROWS - 1), range(self::ROWS - 2, 0));
        $width = 560 / count($path);
        $steps = [];
        foreach ($path as $k => $row) {
            $steps[] = [
                'row' => $row,
                'x' => round(20 + $k * $width + 2, 1),
                'y' => self::rowY($row) - 5,
                'w' => round($width - 4, 1),
                'head' => $k < self::ROWS ? $row > self::BAND + 3 : $row >= self::BAND,
            ];
        }

        return $steps;
    }

    /**
     * Where the demo's default band is drawn: its top and height in the stage.
     *
     * @return array{y: float, h: float}
     */
    public static function bandBox(int $low = self::BAND): array
    {
        return ['y' => self::rowY($low + 3) - 10, 'h' => self::rowY($low) - self::rowY($low + 3) + 20];
    }

    /** Where a note sits on the two ends' meter, B♭1 to C7 by semitones (the hertz on a log scale), in percent. */
    public static function meterAt(int $midi): float
    {
        return round(($midi - self::LOW) / (self::HIGH - self::LOW) * 100, 2);
    }

    /**
     * The archive's timeline, 1741 to 1883: each row's year, where it sits along the line, in percent, and whether its
     * year is written above the line (when it sits close after a year written below, so the two don't collide).
     *
     * @param  list<array{0: string}>  $rows
     * @return list<array{year: string, at: float, up: bool}>
     */
    public static function timeline(array $rows): array
    {
        $years = array_map(static fn (array $row): int => (int) $row[0], $rows);
        if ($years === []) {
            return [];
        }
        $first = min($years);
        $span = max(max($years) - $first, 1);

        $points = [];
        $last = null;
        foreach ($years as $year) {
            $at = round(($year - $first) / $span * 100, 2);
            $up = $last !== null && ! $last['up'] && $at - $last['at'] < 10;
            $points[] = $last = ['year' => (string) $year, 'at' => $at, 'up' => $up];
        }

        return $points;
    }

    /** A plate's number in Roman numerals (I to XII), as an old printed record numbers them. */
    public static function plate(string $words, int $n): string
    {
        $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$n - 1] ?? (string) $n;

        return str_replace(':n', $roman, $words);
    }

    /**
     * Each unit's title, its line, its kind (its index into the page's `unit_kinds`) and its lessons: the lesson's name
     * and its address (a lesson folded into another's page is that page's #lesson-n); a course without lesson pages
     * lists the names alone.
     *
     * @param  array<string, mixed>  $page  the course's words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null  $outline
     * @return list<array{title: string, say: string, lessons: list<array{name: string, url: string|null}>}>
     */
    public static function units(array $page, ?array $outline): array
    {
        $byUnit = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            $byUnit[(int) $lessonPage['unitNumber']][] = $lessonPage;
        }
        $says = array_values((array) ($page['units_say'] ?? []));
        $units = [];

        foreach (array_values($outline['units'] ?? []) as $u => $unit) {
            $lessons = [];
            foreach ($byUnit[$u + 1] ?? [] as $lessonPage) {
                $group = array_values((array) $lessonPage['lessons']);
                foreach ($group as $i => $lesson) {
                    $lessons[] = [
                        'name' => (string) $lesson['name'],
                        'url' => $lessonPage['url'].(count($group) > 1 ? '#lesson-'.($i + 1) : ''),
                    ];
                }
            }
            if ($lessons === []) {
                $lessons = array_map(static fn (string $name): array => ['name' => $name, 'url' => null], $unit['lessons']);
            }
            $units[] = ['title' => $unit['title'], 'say' => (string) ($says[$u] ?? ''), 'lessons' => $lessons];
        }

        return $units;
    }
}
