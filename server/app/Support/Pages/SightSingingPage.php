<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * What the Sight-singing course page (site/courses/sight-singing, Hillel's design of 2026-11-18) draws and reads beyond its
 * words, so the view stays markup: the music on it (staves, the treble clef, key signatures and notes, in the song pages'
 * own engraved outlines from web/js/engrave-glyphs.js), the demo's line (the course's own, from its course file), the
 * sol-fa ladder at its real semitone heights, the lessons as numbered bars linked to their pages, and the hymn board's
 * unit numbers, read from the course file. The words are lang/en/site.php courses.pages.sight-singing; the sounds are
 * js/public/sight-singing.js.
 */
final class SightSingingPage
{
    private const SLUG = 'sight-singing';

    /** The demo's line: the first line read in lesson ss-1-challenge ("A line, read cold"), in semitones above do. */
    public const DEMO_LESSON = 'ss-1-challenge';

    /** The same line, for a checkout without the course file (tests/Feature/Site/SightSingingPageTest keeps them equal). */
    private const DEMO_FALLBACK = ['notes' => [4, 5, 7, 5, 4, 2, 0], 'tempo' => 66];

    /**
     * The demo's keys, each with where do sits on the treble stave (in steps from the bottom line, E4: 0, so middle C is
     * -2) and as MIDI, and its signature: [step on the stave, sharp or flat], in the order they're written (D: F sharp on
     * the top line, then C sharp in the third space; F: B flat on the middle line; G: F sharp).
     */
    public const KEYS = [
        'c' => ['do' => -2, 'midi' => 60, 'signature' => []],
        'd' => ['do' => -1, 'midi' => 62, 'signature' => [[8, 'sharp'], [5, 'sharp']]],
        'f' => ['do' => 1, 'midi' => 65, 'signature' => [[4, 'flat']]],
        'g' => ['do' => 2, 'midi' => 67, 'signature' => [[8, 'sharp']]],
    ];

    /** The sol-fa ladder's rungs: semitones above do, do to high do (the half steps, mi to fa and ti to do, closer). */
    public const RUNGS = [0, 2, 4, 5, 7, 9, 11, 12];

    /** The outcomes rise like the scale, do to so: each one's semitones above do (the half step at fa). */
    public const OUTCOME_STEPS = [0, 2, 4, 5, 7];

    /** Each song, and what marks it in the course file (its words or its song template's id). */
    private const SONG_MARKS = ['Ode to Joy' => 'Ode to Joy', 'Amazing Grace' => 'amazing-grace', 'Michael, Row the Boat Ashore' => 'michael-row'];

    /** Each unit's note-head shape on the lessons' staff (the four-shape system's: sol, mi, fa, la; then a chord). */
    public const UNIT_SHAPES = ['oval', 'diamond', 'triangle', 'rect', 'chord'];

    /** The major scale's steps by semitones above do. */
    private const SCALE = [0 => 0, 2 => 1, 4 => 2, 5 => 3, 7 => 4, 9 => 5, 11 => 6];

    /** The demo's stave: its lines' top and a half space (a staff space of 16). */
    private const TOP = 56;

    private const HALF = 8;

    /** The engraved outlines are drawn with a staff space of 10; the demo's is 16. */
    public const SCALE_DEMO = 1.6;

    private const HEAD = 11.8;

    /** @var array<string, array{d: string}>|null */
    private static ?array $glyphs = null;

    /** @var array<string, mixed>|null */
    private static ?array $course = null;

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

        return 7 * intdiv($semitones - $within, 12) + (self::SCALE[$within] ?? 0);
    }

    /**
     * The engraved outlines the page draws, as one hidden SVG of paths (ids "ss-<name>"), once a page.
     *
     * @param  list<string>  $names
     */
    public static function glyphDefs(array $names): HtmlString
    {
        $glyphs = self::glyphs();
        $paths = '';
        foreach ($names as $name) {
            if (isset($glyphs[$name])) {
                $paths .= '<path id="ss-'.e($name).'" d="'.e($glyphs[$name]['d']).'"/>';
            }
        }

        return new HtmlString('<svg width="0" height="0" class="cp-ss-defs" aria-hidden="true" focusable="false"><defs>'.$paths.'</defs></svg>');
    }

    /**
     * The demo's line: its notes in semitones above do, its tempo, and the lesson it's from (its number in the course,
     * its name and its page's address, when the course has lesson pages).
     *
     * @return array{notes: list<int>, tempo: int, number: int, name: string, url: string|null}
     */
    public static function demo(): array
    {
        $line = self::DEMO_FALLBACK;
        $number = 0;
        $name = '';
        $count = 0;
        foreach ((array) (self::course()['units'] ?? []) as $unit) {
            foreach ((array) ($unit['lessons'] ?? []) as $lesson) {
                $count++;
                if (! is_array($lesson) || ($lesson['id'] ?? null) !== self::DEMO_LESSON) {
                    continue;
                }
                $number = $count;
                $name = self::words($lesson['title'] ?? '');
                foreach ((array) ($lesson['steps'] ?? []) as $step) {
                    $notes = is_array($step) ? ($step['exercise']['notes'] ?? null) : null;
                    if (($step['kind'] ?? null) === 'play' && is_array($notes) && $notes !== []) {
                        $line = ['notes' => array_values(array_map(static fn (mixed $n): int => (int) (is_array($n) ? ($n['step'] ?? 0) : 0), $notes)), 'tempo' => (int) ($step['exercise']['tempo'] ?? 66)];
                        break;
                    }
                }
            }
        }

        $url = null;
        foreach (Lessons::pages(self::SLUG) as $page) {
            $ids = array_column((array) $page['lessons'], 'id');
            $at = array_search(self::DEMO_LESSON, $ids, true);
            if ($at !== false) {
                $url = (string) $page['url'].($at > 0 ? '#lesson-'.($at + 1) : '');
            }
        }

        return ['notes' => $line['notes'], 'tempo' => $line['tempo'], 'number' => $number, 'name' => $name, 'url' => $url];
    }

    /** A place on the demo's stave (steps from the bottom line) as its height in the drawing. */
    public static function y(int $position): int
    {
        return self::TOP + (8 - $position) * self::HALF;
    }

    /**
     * The demo's line drawn in each key, a note at a time (each note its own drawing, centred on 0 across, so the page
     * can space them as the width allows): each key's signature (the signs' outlines and places, beside the clef), and
     * each note's step of the scale, and in each key its height, its stem and whether it takes middle C's ledger line
     * (Guido's yellow C line). Stems go up below the middle line, on the head's right; down from it, on the left.
     *
     * @param  list<int>  $notes  semitones above do
     * @return array{signatures: array<string, list<array{glyph: string, x: int, y: int}>>, notes: list<array{semis: int, degree: int, keys: array<string, array{y: int, stem: array{x: float, y: int, h: int}, ledger: bool}>}>}
     */
    public static function staves(array $notes): array
    {
        $half = self::HEAD * self::SCALE_DEMO / 2;
        $signatures = [];
        foreach (self::KEYS as $key => $info) {
            $signatures[$key] = [];
            foreach ($info['signature'] as $i => [$position, $sign]) {
                $signatures[$key][] = ['glyph' => $sign === 'sharp' ? 'accidentalSharp' : 'accidentalFlat', 'x' => 60 + $i * 16, 'y' => self::y($position)];
            }
        }
        $drawn = [];
        foreach ($notes as $semitones) {
            $degree = self::step($semitones);
            $keys = [];
            foreach (self::KEYS as $key => $info) {
                $position = $info['do'] + $degree;
                $y = self::y($position);
                $keys[$key] = [
                    'y' => $y,
                    'stem' => $position < 4 ? ['x' => round($half - 1.9, 2), 'y' => $y - 54, 'h' => 53] : ['x' => round(-$half, 2), 'y' => $y + 1, 'h' => 53],
                    'ledger' => $position <= -2,
                ];
            }
            $drawn[] = ['semis' => $semitones, 'degree' => (($degree % 7) + 7) % 7, 'keys' => $keys];
        }

        return ['signatures' => $signatures, 'notes' => $drawn];
    }

    /** A notehead's left edge from its centre, on the demo's stave. */
    public static function headX(): float
    {
        return round(-self::HEAD * self::SCALE_DEMO / 2, 2);
    }

    /**
     * The hero's strip: the C major scale from middle C up to the C above, stepping up a treble stave to do, each note's
     * place across in percent and its height, with the half steps (mi to fa, ti to do) marked.
     *
     * @return array{notes: list<array{x: float, y: int, ledger: bool, home: bool}>, halves: list<array{from: float, to: float, y: int}>}
     */
    public static function strip(): array
    {
        $notes = [];
        foreach ([0, 2, 4, 5, 7, 9, 11, 12] as $i => $semitones) {
            $position = -2 + self::step($semitones);
            $notes[] = ['x' => round(16 + $i * 10.8, 2), 'y' => 68 - $position * 6, 'ledger' => $position <= -2, 'home' => $i === 7];
        }
        $halves = [];
        foreach ([[2, 3], [6, 7]] as [$a, $b]) {
            $halves[] = ['from' => $notes[$a]['x'], 'to' => $notes[$b]['x'], 'y' => min($notes[$a]['y'], $notes[$b]['y']) - 12];
        }

        return ['notes' => $notes, 'halves' => $halves];
    }

    /**
     * The four-shape scale of the shape-note books (fa sol la fa sol la mi fa), stepping up a small stave from middle C:
     * each note's shape, its syllable's index in `fasola` (fa, sol, la, mi) and its place.
     *
     * @return list<array{shape: string, name: int, x: int, y: int, ledger: bool}>
     */
    public static function shapeScale(): array
    {
        $shapes = ['triangle', 'oval', 'rect', 'triangle', 'oval', 'rect', 'diamond', 'triangle'];
        $names = [0, 1, 2, 0, 1, 2, 3, 0];
        $notes = [];
        foreach ($shapes as $i => $shape) {
            $position = $i - 2;
            $notes[] = ['shape' => $shape, 'name' => $names[$i], 'x' => 42 + $i * 28, 'y' => 52 - $position * 5, 'ledger' => $position <= -2];
        }

        return $notes;
    }

    /**
     * Each unit's title, its line, its range and its shape, and its lessons as numbered bars: the lesson's number in the
     * course (01 to 20), its name, its page's search title (for a challenge, folded into the unit's last page: none) and
     * its address. A course without lesson pages lists the names alone.
     *
     * @param  array<string, mixed>  $page  the course's words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null  $outline
     * @return list<array{n: int, title: string, say: string, range: string, shape: string, lessons: list<array{number: string, name: string, line: string, url: string|null}>}>
     */
    public static function units(array $page, ?array $outline): array
    {
        $byUnit = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            $byUnit[(int) $lessonPage['unitNumber']][] = $lessonPage;
        }
        $says = array_values((array) ($page['units_say'] ?? []));
        $ranges = array_values((array) ($page['love']['ranges'] ?? []));
        $units = [];
        $number = 0;

        foreach (array_values($outline['units'] ?? []) as $u => $unit) {
            $linked = [];
            foreach ($byUnit[$u + 1] ?? [] as $lessonPage) {
                $group = array_values((array) $lessonPage['lessons']);
                foreach ($group as $i => $lesson) {
                    $linked[] = [
                        'name' => (string) $lesson['name'],
                        'line' => $i === 0 ? (string) $lessonPage['title'] : '',
                        'url' => $lessonPage['url'].(count($group) > 1 ? '#lesson-'.($i + 1) : ''),
                    ];
                }
            }
            if ($linked === []) {
                $linked = array_map(static fn (string $name): array => ['name' => $name, 'line' => '', 'url' => null], $unit['lessons']);
            }
            $lessons = [];
            foreach ($linked as $lesson) {
                $lessons[] = ['number' => str_pad((string) ++$number, 2, '0', STR_PAD_LEFT), ...$lesson];
            }
            $units[] = [
                'n' => $u + 1,
                'title' => $unit['title'],
                'say' => (string) ($says[$u] ?? ''),
                'range' => (string) ($ranges[$u] ?? ''),
                'shape' => self::UNIT_SHAPES[$u] ?? 'oval',
                'lessons' => $lessons,
            ];
        }

        return $units;
    }

    /**
     * The songs on the hymn board, each with the unit it's first sung in, read from the course file (by the song's words
     * or its template), or null when the course file doesn't name it.
     *
     * @param  array<array-key, mixed>  $songs  the page's songs, [title, credit], in its language
     * @return list<array{title: string, credit: string, unit: int|null}>
     */
    public static function hymnBoard(array $songs): array
    {
        $english = array_values((array) __('site.courses.pages.'.self::SLUG.'.songs', [], 'en'));
        $units = array_values(array_map(static fn (mixed $unit): string => (string) json_encode($unit, JSON_UNESCAPED_UNICODE), (array) (self::course()['units'] ?? [])));
        $board = [];
        foreach (array_values($songs) as $i => $song) {
            if (! is_array($song) || ! is_string($song[0] ?? null)) {
                continue;
            }
            $mark = self::SONG_MARKS[(string) ($english[$i][0] ?? '')] ?? null;
            $unit = null;
            foreach ($units as $u => $json) {
                if ($mark !== null && str_contains($json, $mark)) {
                    $unit = $u + 1;
                    break;
                }
            }
            $board[] = ['title' => $song[0], 'credit' => (string) ($song[1] ?? ''), 'unit' => $unit];
        }

        return $board;
    }

    /** A small roman numeral (i to x), as the science's points are numbered. */
    public static function roman(int $n): string
    {
        return ['', 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'][$n] ?? (string) $n;
    }

    /** @return array<string, array{d: string}> */
    private static function glyphs(): array
    {
        if (self::$glyphs !== null) {
            return self::$glyphs;
        }
        $path = config('app.web_path').'/js/engrave-glyphs.js';
        $source = is_file($path) ? (string) file_get_contents($path) : '';
        $glyphs = preg_match('/export const GLYPHS = (\{.*?\});\n/s', $source, $m) === 1 ? json_decode($m[1], true) : null;

        return self::$glyphs = is_array($glyphs) ? $glyphs : [];
    }

    /** @return array<string, mixed> */
    private static function course(): array
    {
        if (self::$course !== null) {
            return self::$course;
        }
        $path = config('app.web_path').'/course/plus/'.self::SLUG.'/course.json';
        $course = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::$course = is_array($course) ? $course : [];
    }

    /** A course file's words in the page's language, else English. */
    private static function words(mixed $text): string
    {
        if (is_string($text)) {
            return $text;
        }
        $text = (array) $text;

        return (string) ($text[app()->getLocale()] ?? $text['en'] ?? '');
    }
}
