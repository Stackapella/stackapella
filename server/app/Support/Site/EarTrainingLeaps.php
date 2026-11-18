<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * The Ear training course page's leaps (site/courses/ear-training, Hillel's design of 2026-11-18), from real notes, so
 * every leap the page draws or plays is the one the course teaches.
 *
 * - songs(): the three song openings lesson 1 (Song starts) keeps each leap with. Amazing Grace is its song template's
 *   (web/templates/amazing-grace.json, the tune's first four words); Oh When the Saints and Twinkle, Twinkle have no
 *   template, so theirs are the lesson's own notes (its first listen, in scale steps from do). Each opening's leap is
 *   the first two notes a three (4 semitones), a four (5) or a five (7) apart, found in the notes, never assumed.
 * - ladder(): the hero's eight scale steps, do to do, with each song's leap drawn where it really sits: Saints do to
 *   mi, Twinkle do to so, Amazing Grace so (below) to do, drawn an octave up as so to high do.
 * - lessons(): every lesson by unit, its first line from the course file (its own opening words) and the lesson page
 *   it's on (a unit's challenge is folded into the page before it), or none when the course has no lesson pages.
 *
 * A file that's missing gives empty openings, never an error.
 */
final class EarTrainingLeaps
{
    /** The leaps by their name, in semitones. */
    public const LEAPS = ['three' => 4, 'four' => 5, 'five' => 7];

    /** The scale's eight steps, do to high do, in semitones. */
    public const SCALE = [0, 2, 4, 5, 7, 9, 11, 12];

    /** Where Saints and Twinkle are sung from: do as middle C. Amazing Grace keeps its template's own key. */
    private const DO = 60;

    /** A card's height: so below do (-5) to so above it (7), in semitones from do. */
    private const LOW = -5;

    private const HIGH = 7;

    /** The ladder's drawing (an SVG 520 by 420): the rungs' first y, their gap, and where each leap leaves and lands. */
    private const RUNG_Y = 385;

    private const RUNG_GAP = 46;

    private const FROM_X = 110;

    private const LAND_X = ['three' => 190, 'four' => 236, 'five' => 262];

    /**
     * The three openings, three then four then five.
     *
     * @return list<array{key: string, semis: int, title: string, tempo: int, from: int, arc: string, notes: list<array{at: float, len: float, midi: int, rel: int, word: string, x: float, y: float, leap: bool}>}>
     */
    public static function songs(): array
    {
        $lesson = self::lesson('ear-1-songs');
        $learn = (array) ($lesson['steps'][0] ?? []);
        $groups = self::groups(array_values(array_filter((array) ($learn['listen']['notes'] ?? []), 'is_array')));
        $tempo = (int) ($learn['listen']['tempo'] ?? 84);
        $titles = array_map(static fn (mixed $s): string => is_array($s) ? self::words($s['title'] ?? '') : '', array_values((array) ($learn['steps'] ?? [])));
        $words = (array) Lang::get('site.courses.pages.ear-training.et.words');

        $songs = [];
        foreach (array_keys(self::LEAPS) as $i => $key) {
            $raw = [];
            $pace = $tempo;
            if ($key === 'four') {
                // Amazing Grace from its template: the tune's notes before "how" (beat 6), the slurred note on "-ing" too.
                $song = self::json(self::web('templates/amazing-grace.json'));
                $tune = collect((array) ($song['parts'] ?? []))->first(static fn (mixed $p): bool => is_array($p) && ($p['melody'] ?? false) === true);
                $doh = is_array($tune) ? self::doOf($song) : 67;
                foreach ((array) (is_array($tune) ? ($tune['guide']['notes'] ?? []) : []) as $note) {
                    if (is_array($note) && (float) ($note['beat'] ?? 0) < 6) {
                        $raw[] = [(float) $note['beat'], (float) ($note['length'] ?? 1), (int) ($note['pitch'] ?? $doh), $doh, (string) ($note['lyric'] ?? '')];
                    }
                }
                $title = is_string($song['title'] ?? null) ? $song['title'] : ($titles[$i] ?? '');
                $pace = (int) ($song['tempo'] ?? $tempo);
            } else {
                $sung = array_values(array_filter((array) ($words[$key] ?? []), 'is_string'));
                $w = 0;
                foreach ($groups[$i] ?? [] as $note) {
                    $raw[] = [(float) ($note['beat'] ?? 0), (float) ($note['length'] ?? 1), self::DO + (int) ($note['step'] ?? 0), self::DO, $sung[$w++] ?? ''];
                }
                $title = $titles[$i] ?? '';
            }
            if ($raw === []) {
                continue;
            }
            $songs[] = self::opening($key, $title, $pace, $raw);
        }

        return $songs;
    }

    /**
     * The hero's ladder: its rungs, and each song's leap on it.
     *
     * @return array{rungs: list<array{n: int, y: int, top: float}>, leaps: list<array{key: string, title: string, from: int, to: int, fromMidi: int, toMidi: int, path: string, x0: int, y0: int, x1: int, y1: int, left: float, top: float}>}
     */
    public static function ladder(): array
    {
        $rungs = [];
        foreach (self::SCALE as $i => $semi) {
            $y = self::RUNG_Y - $i * self::RUNG_GAP;
            $rungs[] = ['n' => $i + 1, 'y' => $y, 'top' => round($y / 420 * 100, 3)];
        }

        $leaps = [];
        foreach (self::songs() as $song) {
            $a = $song['notes'][$song['from']]['rel'];
            $b = $song['notes'][$song['from'] + 1]['rel'];
            // A leap from below do (Amazing Grace's so) is drawn an octave up, so to high do, the same leap.
            $lift = $a < 0 ? 12 : 0;
            $from = array_search($a + $lift, self::SCALE, true);
            $to = array_search($b + $lift, self::SCALE, true);
            if ($from === false || $to === false) {
                continue;
            }
            $x0 = self::FROM_X;
            $x1 = self::LAND_X[$song['key']];
            $y0 = self::RUNG_Y - $from * self::RUNG_GAP;
            $y1 = self::RUNG_Y - $to * self::RUNG_GAP;
            // An arc rising from the note it leaves and stopping at the edge of the one it lands on.
            $cx = (int) round($x0 + ($x1 - $x0) * 0.45);
            $cy = min($y0, $y1) - 58;
            $leaps[] = [
                'key' => $song['key'],
                'title' => $song['title'],
                'from' => $from + 1,
                'to' => $to + 1,
                'fromMidi' => self::DO + $a + $lift,
                'toMidi' => self::DO + $b + $lift,
                'path' => "M{$x0},".($y0 - 12)." Q{$cx},{$cy} ".($x1 - 12).','.($y1 - 6),
                'x0' => $x0,
                'y0' => $y0,
                'x1' => $x1,
                'y1' => $y1,
                'left' => round(($x1 + 18) / 520 * 100, 3),
                'top' => round($y1 / 420 * 100, 3),
            ];
        }

        return ['rungs' => $rungs, 'leaps' => $leaps];
    }

    /**
     * Every lesson by unit, each with its first line (the course's own words, in the page's language) and its lesson
     * page; `$outline` is SitePages::courseOutline's, for the units' and lessons' names.
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}  $outline
     * @return list<array{title: string, lessons: list<array{n: string, name: string, line: string, url: string|null}>}>
     */
    public static function lessons(array $outline): array
    {
        $file = self::json(self::web('course/plus/ear/course.json'));
        $pages = [];
        foreach (Lessons::pages('ear-training') as $page) {
            foreach ((array) ($page['ids'] ?? []) as $id) {
                $pages[(string) $id] ??= (string) $page['url'];
            }
        }

        $units = [];
        $number = 0;
        foreach ($outline['units'] as $u => $unit) {
            $lessons = array_values(array_filter((array) (is_array($file['units'][$u] ?? null) ? ($file['units'][$u]['lessons'] ?? []) : []), 'is_array'));
            $rows = [];
            foreach ($unit['lessons'] as $l => $name) {
                $lesson = $lessons[$l] ?? [];
                $id = (string) ($lesson['id'] ?? '');
                $rows[] = ['n' => str_pad((string) ++$number, 2, '0', STR_PAD_LEFT), 'name' => $name, 'line' => self::firstLine($lesson), 'url' => $pages[$id] ?? null];
            }
            $units[] = ['title' => $unit['title'], 'lessons' => $rows];
        }

        return $units;
    }

    /**
     * The page's facts' sources ([text, label, url] each), keyed for site/courses/_cites and numbered in their order.
     *
     * @param  array<array-key, mixed>  $facts
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sources(array $facts): array
    {
        $sources = [];
        foreach (array_values($facts) as $i => $fact) {
            if (is_array($fact)) {
                $sources['f'.$i] = [(string) ($fact[1] ?? ''), (string) ($fact[2] ?? '')];
            }
        }

        return $sources;
    }

    /** One of the page's own marked lines as HTML: | breaks it, *words* are the words in colour. Escaped first. */
    public static function marked(string $line): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*(.+?)\*/u', '<em>$1</em>', str_replace('|', '<br>', e($line))));
    }

    /**
     * One opening, placed on its card: x by its beat, y by its pitch from do; its leap marked.
     *
     * @param  list<array{0: float, 1: float, 2: int, 3: int, 4: string}>  $raw  [beat, length, midi, do's midi, word]
     * @return array{key: string, semis: int, title: string, tempo: int, from: int, arc: string, notes: list<array{at: float, len: float, midi: int, rel: int, word: string, x: float, y: float, leap: bool}>}
     */
    private static function opening(string $key, string $title, int $tempo, array $raw): array
    {
        // Across the card by when each note starts, the last at about three quarters of the way.
        $start = $raw[0][0];
        $last = $start;
        foreach ($raw as $note) {
            $last = max($last, $note[0]);
        }
        $span = max($last - $start, 1.0);
        $semis = self::LEAPS[$key];

        $from = -1;
        foreach ($raw as $i => $note) {
            if (isset($raw[$i + 1]) && $raw[$i + 1][2] - $note[2] === $semis) {
                $from = $i;
                break;
            }
        }

        $notes = [];
        foreach ($raw as $i => [$beat, $len, $midi, $doh, $word]) {
            $rel = $midi - $doh;
            $notes[] = [
                'at' => $beat - $start,
                'len' => $len,
                'midi' => $midi,
                'rel' => $rel,
                'word' => rtrim($word, ',.;:!?'),
                'x' => round(6 + ($beat - $start) / $span * 70, 3),
                'y' => self::height($rel),
                'leap' => $i === $from || $i === $from + 1,
            ];
        }

        // The leap's arc on the card (a 100 by 100 box over it): from just above its first word to its second.
        $a = $notes[max($from, 0)];
        $b = $notes[max($from, 0) + 1] ?? $a;
        $arc = 'M'.($a['x'] + 3).','.($a['y'] - 9).' Q'.(($a['x'] + $b['x']) / 2 + 2).','.(min($a['y'], $b['y']) - 22).' '.($b['x'] + 1).','.($b['y'] - 10);

        return ['key' => $key, 'semis' => $semis, 'title' => $title, 'tempo' => $tempo, 'from' => max($from, 0), 'arc' => $arc, 'notes' => $notes];
    }

    /**
     * The card's lines, so below do to so above it, as heights in percent.
     *
     * @return list<float>
     */
    public static function lines(): array
    {
        return array_map(self::height(...), [-5, -3, -1, 0, 2, 4, 5, 7]);
    }

    /** A pitch's height on a card, in percent from its top: so above do near the top, so below near the bottom. */
    public static function height(int $rel): float
    {
        return round(10 + (self::HIGH - $rel) / (self::HIGH - self::LOW) * 80, 3);
    }

    /**
     * The lesson's notes split into its songs: a new song wherever a note starts after the last one ended.
     *
     * @param  list<array<array-key, mixed>>  $notes
     * @return list<list<array<array-key, mixed>>>
     */
    private static function groups(array $notes): array
    {
        $groups = [];
        $end = null;
        foreach ($notes as $note) {
            $beat = (float) ($note['beat'] ?? 0);
            if ($end === null || $beat > $end + 0.01) {
                $groups[] = [];
            }
            $groups[count($groups) - 1][] = $note;
            $end = $beat + (float) ($note['length'] ?? 0);
        }

        return $groups;
    }

    /**
     * The template's do (its key's tonic) in the octave from middle C: G is 67, so a tune in G starting on D4 opens so
     * below to do.
     *
     * @param  array<array-key, mixed>  $song
     */
    private static function doOf(array $song): int
    {
        $names = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];
        $key = (string) ($song['key'] ?? 'C');
        $pc = ($names[strtoupper($key[0] ?? 'C')] ?? 0) + (str_contains($key, '#') ? 1 : 0) - (str_contains(substr($key, 1), 'b') ? 1 : 0);

        return 60 + (($pc % 12) + 12) % 12;
    }

    /**
     * A lesson's first line: its learn step's headline, to the end of its first sentence.
     *
     * @param  array<array-key, mixed>  $lesson
     */
    private static function firstLine(array $lesson): string
    {
        $headline = self::words(is_array($lesson['steps'][0] ?? null) ? ($lesson['steps'][0]['headline'] ?? '') : '');
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}¿¡"])/u', $headline, 2);

        return trim((string) ($parts[0] ?? $headline));
    }

    /** @return array<array-key, mixed> */
    private static function lesson(string $id): array
    {
        $file = self::json(self::web('course/plus/ear/course.json'));
        foreach ((array) ($file['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $id) {
                    return $lesson;
                }
            }
        }

        return [];
    }

    /** A course file's words in the page's language, else English. */
    private static function words(mixed $words): string
    {
        if (is_string($words)) {
            return $words;
        }
        if (! is_array($words)) {
            return '';
        }
        $said = $words[app()->getLocale()] ?? $words['en'] ?? '';

        return is_string($said) ? $said : '';
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
