<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;

/**
 * The Improvising a harmony course page's pictures (site/courses/improvising-harmony, Hillel's "tunebook" design of
 * 2026-11-18), from real notes, so the view holds no logic of its own.
 *
 * - lane(): Michael, Row the Boat Ashore's first line on eight solfège rows, low do to high do, each with its shape-note
 *   head (the four-shape fa, sol, la, mi: do and fa a triangle, re and so an oval, mi and la a square, ti a diamond). The
 *   tune is the song's template (its soprano, in the octave lesson 1 sings it); the part above is lesson 1's own
 *   (imp-1-third, its first step's notes), its chords lesson 1's. Each note of the part also carries how lit it is while
 *   the lights fade, and "you choose" lights every other note of the chord under each tune note.
 * - strip(): lesson 1's second step, the part above held on the line's two long notes over the moving tune.
 * - index(): the course's lessons numbered 1 to 16 by unit, each linked to the lesson page it's on (a unit's challenge
 *   is on the unit's last lesson page), with that page's title under the lesson that opens it.
 *
 * A file that's missing gives empty lanes and an empty index, never an error.
 */
final class ImprovisingHarmonyPage
{
    public const COURSE = 'improvising-harmony';

    public const LESSON = 'imp-1-third';

    public const SONG = 'michael-row';

    /** The line's length in beats: "Michael, row the boat ashore, Hallelujah!". */
    public const SPAN = 16;

    /** Semitones above do of each row, low do to high do. */
    private const DEGREES = [0, 2, 4, 5, 7, 9, 11, 12];

    /** Each row's solfège name, low do to high do. */
    private const NAMES = ['do', 're', 'mi', 'fa', 'so', 'la', 'ti', 'do'];

    /** Each row's head in four-shape notation (fa triangle, sol oval, la square, mi diamond), low do to high do. */
    private const SHAPES = ['fa', 'sol', 'la', 'fa', 'sol', 'la', 'mi', 'fa'];

    private const KEYS = ['C' => 0, 'C#' => 1, 'Db' => 1, 'D' => 2, 'D#' => 3, 'Eb' => 3, 'E' => 4, 'F' => 5, 'F#' => 6, 'Gb' => 6, 'G' => 7, 'G#' => 8, 'Ab' => 8, 'A' => 9, 'A#' => 10, 'Bb' => 10, 'B' => 11];

    /**
     * @return array{tempo: int, span: int, rows: list<array{name: string, shape: string}>, tune: list<array<string, mixed>>, part: list<array<string, mixed>>, choices: list<array<string, mixed>>, chords: list<array{at: float, until: float, midis: list<int>}>}
     */
    public static function lane(): array
    {
        $song = self::json(self::web('templates/'.self::SONG.'.json'));
        $listen = (array) (self::lesson(self::LESSON)['steps'][0]['listen'] ?? []);
        $partNotes = self::notes((array) ($listen['notes'] ?? []));
        $lessonTune = self::notes((array) ($listen['with'][0]['notes'] ?? []));
        $template = self::melody($song);

        // The template's tune, moved to the octave lesson 1 sings it in (its first note against the lesson's).
        $shift = $template !== [] && $lessonTune !== [] ? (60 + (int) $lessonTune[0]['step']) - (int) $template[0]['pitch'] : -12;
        $tune = array_map(static fn (array $n): array => ['at' => (float) $n['beat'], 'len' => (float) $n['length'], 'midi' => (int) $n['pitch'] + $shift, 'word' => (string) ($n['lyric'] ?? '')], $template);
        $part = array_map(static fn (array $n): array => ['at' => (float) $n['beat'], 'len' => (float) $n['length'], 'midi' => 60 + (int) $n['step'], 'word' => (string) ($n['lyric'] ?? '')], $partNotes);

        // Do: the key's tonic at or below the tune's lowest note.
        $tonic = self::KEYS[(string) ($song['key'] ?? 'F')] ?? 5;
        $low = $tune === [] ? 53 : min(array_column($tune, 'midi'));
        $do = $low - ((($low - $tonic) % 12) + 12) % 12;

        $chords = [];
        $list = array_values(array_filter((array) ($listen['chords'] ?? []), 'is_array'));
        foreach ($list as $i => $chord) {
            $root = (int) ($chord['root'] ?? 0);
            $third = ($chord['quality'] ?? 'maj') === 'min' ? 3 : 4;
            $chords[] = ['at' => (float) ($chord['beat'] ?? 0), 'until' => (float) ($list[$i + 1]['beat'] ?? self::SPAN), 'pcs' => [$root % 12, ($root + $third) % 12, ($root + 7) % 12], 'midis' => self::below(41, [$root, $root + $third, $root + 7])];
        }

        $rows = [];
        foreach (array_reverse(array_keys(self::NAMES)) as $k) {
            $rows[] = ['name' => self::NAMES[$k], 'shape' => self::SHAPES[$k]];
        }

        $count = count($part);
        $placed = [];
        foreach ($part as $i => $note) {
            $at = $count > 1 ? $i / ($count - 1) : 0;
            $placed[] = [...self::place($note, $do), 'fade' => match (true) {
                $at < 0.3 => 'on',
                $at < 0.6 => 'half',
                $at < 0.8 => 'dim',
                default => 'off',
            }];
        }

        // You choose: every other note of the chord, on the rows, under each tune note.
        $choices = [];
        foreach ($tune as $i => $note) {
            $pcs = self::pcsAt($chords, $note['at']);
            foreach (self::DEGREES as $semis) {
                $midi = $do + $semis;
                if (in_array($midi % 12, $pcs, true) && $midi !== $note['midi'] && $midi !== ($part[$i]['midi'] ?? null)) {
                    $choices[] = self::place([...$note, 'midi' => $midi, 'word' => ''], $do);
                }
            }
        }

        return [
            'tempo' => (int) ($listen['tempo'] ?? $song['tempo'] ?? 92),
            'span' => self::SPAN,
            'rows' => $rows,
            'tune' => array_map(static fn (array $n): array => self::place($n, $do), $tune),
            'part' => $placed,
            'choices' => $choices,
            'chords' => array_map(static fn (array $c): array => ['at' => $c['at'], 'until' => $c['until'], 'midis' => $c['midis']], $chords),
        ];
    }

    /**
     * Lesson 1's second step: the part above held on the line's two long notes ("row" and "lu-") while the tune moves,
     * both placed on the same rows as lane(). The step starts after a bar of count-in, taken off here.
     *
     * @return array{tune: list<array<string, mixed>>, part: list<array<string, mixed>>}
     */
    public static function strip(): array
    {
        $steps = (array) (self::lesson(self::LESSON)['steps'] ?? []);
        $held = [];
        $with = [];
        foreach ($steps as $step) {
            if (is_array($step) && ($step['kind'] ?? '') === 'play' && is_array($step['exercise']['with'][0]['notes'] ?? null)) {
                $held = self::notes((array) ($step['exercise']['notes'] ?? []));
                $with = self::notes($step['exercise']['with'][0]['notes']);
                break;
            }
        }
        $start = $with === [] ? 0.0 : (float) $with[0]['beat'];
        $song = self::json(self::web('templates/'.self::SONG.'.json'));
        $tonic = self::KEYS[(string) ($song['key'] ?? 'F')] ?? 5;
        $low = 60 + ($with === [] ? 0 : min(array_map(static fn (array $n): int => (int) $n['step'], $with)));
        $do = $low - ((($low - $tonic) % 12) + 12) % 12;
        $map = static fn (array $n): array => self::place(['at' => (float) $n['beat'] - $start, 'len' => (float) $n['length'], 'midi' => 60 + (int) $n['step'], 'word' => (string) ($n['lyric'] ?? '')], $do);

        return [
            'tune' => array_values(array_filter(array_map($map, $with), static fn (array $n): bool => $n['at'] < self::SPAN)),
            'part' => array_map($map, $held),
        ];
    }

    /**
     * The course's units, each with its lessons numbered through the course and linked to the lesson page each is on.
     *
     * @return list<array{title: string, lessons: list<array{n: string, title: string, url: string|null, sub: string|null}>}>
     */
    public static function index(): array
    {
        $file = self::json(self::web('course/plus/'.self::COURSE.'/course.json'));
        $pages = [];
        foreach (Lessons::pages(self::COURSE) as $page) {
            foreach ((array) ($page['lessons'] ?? []) as $i => $lesson) {
                $id = is_array($lesson) ? (string) ($lesson['id'] ?? '') : '';
                $pages[$id] = ['url' => (string) $page['url'], 'sub' => $i === 0 ? (string) $page['title'] : null];
            }
        }

        $locale = app()->getLocale();
        $words = static fn (mixed $t): string => is_array($t) ? (string) ($t[$locale] ?? $t['en'] ?? '') : (is_string($t) ? $t : '');
        $units = [];
        $n = 0;
        foreach ((array) ($file['units'] ?? []) as $unit) {
            if (! is_array($unit)) {
                continue;
            }
            $lessons = [];
            foreach ((array) ($unit['lessons'] ?? []) as $lesson) {
                if (! is_array($lesson)) {
                    continue;
                }
                $n++;
                $id = (string) ($lesson['id'] ?? '');
                $lessons[] = ['n' => str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'title' => $words($lesson['title'] ?? ''), 'url' => $pages[$id]['url'] ?? null, 'sub' => $pages[$id]['sub'] ?? null];
            }
            $units[] = ['title' => $words($unit['title'] ?? ''), 'lessons' => $lessons];
        }

        return $units;
    }

    /**
     * A heading from the lang file split round its *starred* words, the ones set in the italic serif: [before, starred,
     * after]. A heading with no stars is all `before`.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function em(string $text): array
    {
        $parts = explode('*', $text, 3);

        return count($parts) === 3 ? [$parts[0], $parts[1], $parts[2]] : [str_replace('*', '', $text), '', ''];
    }

    /**
     * A note on the rows: its row from the top (0 is high do), and its place along the line in percent.
     *
     * @param  array{at: float, len: float, midi: int, word: string}  $note
     * @return array{at: float, len: float, midi: int, word: string, row: int, left: float, width: float}
     */
    private static function place(array $note, int $do): array
    {
        $semis = $note['midi'] - $do;
        $k = 0;
        foreach (self::DEGREES as $i => $degree) {
            if (abs($degree - $semis) < abs(self::DEGREES[$k] - $semis)) {
                $k = $i;
            }
        }

        return [...$note, 'row' => 7 - $k, 'left' => round($note['at'] / self::SPAN * 100, 3), 'width' => round($note['len'] / self::SPAN * 100, 3)];
    }

    /**
     * The pitch classes of the chord sounding at `$beat`.
     *
     * @param  list<array{at: float, until: float, pcs: list<int>, midis: list<int>}>  $chords
     * @return list<int>
     */
    private static function pcsAt(array $chords, float $beat): array
    {
        $pcs = [];
        foreach ($chords as $chord) {
            if ($chord['at'] <= $beat) {
                $pcs = $chord['pcs'];
            }
        }

        return $pcs;
    }

    /**
     * A chord's notes, low and soft under the voices: each pitch class at or above `$low`.
     *
     * @param  list<int>  $pitchClasses
     * @return list<int>
     */
    private static function below(int $low, array $pitchClasses): array
    {
        return array_map(static fn (int $pc): int => $low + ((($pc - $low) % 12) + 12) % 12, $pitchClasses);
    }

    /**
     * The template's melody, its first line.
     *
     * @param  array<array-key, mixed>  $song
     * @return list<array<array-key, mixed>>
     */
    private static function melody(array $song): array
    {
        foreach ((array) ($song['parts'] ?? []) as $part) {
            if (is_array($part) && ($part['melody'] ?? false) === true) {
                return array_values(array_filter((array) ($part['guide']['notes'] ?? []), static fn (mixed $n): bool => is_array($n) && (float) ($n['beat'] ?? 0) < self::SPAN && isset($n['pitch'])));
            }
        }

        return [];
    }

    /**
     * A course file's notes that have a beat, a length and a step.
     *
     * @param  array<array-key, mixed>  $notes
     * @return list<array<array-key, mixed>>
     */
    private static function notes(array $notes): array
    {
        return array_values(array_filter($notes, static fn (mixed $n): bool => is_array($n) && isset($n['beat'], $n['length'], $n['step'])));
    }

    /**
     * A lesson of the course, or [] when there's none.
     *
     * @return array<array-key, mixed>
     */
    private static function lesson(string $id): array
    {
        $file = self::json(self::web('course/plus/'.self::COURSE.'/course.json'));
        foreach ((array) ($file['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $id) {
                    return $lesson;
                }
            }
        }

        return [];
    }

    private static function web(string $path): string
    {
        return rtrim((string) config('app.web_path'), '/').'/'.$path;
    }

    /** @return array<array-key, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
