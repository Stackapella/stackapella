<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;

/**
 * The Learn your part for rehearsal course page's pictures (site/courses/learn-your-choir-part, Hillel's design of
 * 2026-11-18), every one drawn from real notes:
 *
 * - hero(): the course's own song (its course file's `parts.template`: O Come, All Ye Faithful), its first two lines
 *   (the first section's phrases 1 and 2, the lines lesson 1 sings), each of its four parts as a lane of notes with its
 *   words, and the bar lines numbered from the template's upbeat and time signature.
 * - take(): a sample take of each part's same lines, two of its notes sung off the note, for the card that shows how a
 *   take is said (always marked as a sample on the page).
 * - grace(): Amazing Grace's first line in its four parts, the beginner course's song (unit 7 sings all four).
 * - lessons(): each lesson's link, its unit's page and the lesson's place on it (site/lesson.blade.php's lesson-<n>).
 *
 * Each note is [at, len, midi, word, left, width, y]: its beat, its length in beats, its pitch, the word sung on it
 * (trimmed of hyphens and stops), its place across its lane in percent, and its height in its lane from 0 (the part's
 * lowest note here) to 1 (its highest). A file that's missing gives empty lanes, never an error.
 */
final class ChoirPartScore
{
    public const SONG = 'o-come-all-ye-faithful';

    public const PARTS = ['soprano', 'alto', 'tenor', 'bass'];

    /** The sample take's two notes sung off: which note, and how far off its place (a share of the lane). */
    private const MISSES = [4 => 0.2, 9 => -0.2];

    /**
     * @return array{song: string, title: string, tempo: int, span: float, from: int, to: int, bars: list<array{n: int, left: float}>, parts: array<string, list<array{at: float, len: float, midi: int, word: string, left: float, width: float, y: float}>>}
     */
    public static function hero(): array
    {
        $slug = self::songSlug();
        $song = self::json(base_path('database/templates/'.$slug.'.json'));
        $span = self::firstLines($song, 2, 16.0);
        $bars = self::bars($song, $span);

        return [
            'song' => $slug,
            'title' => (string) ($song['title'] ?? ''),
            'tempo' => (int) ($song['tempo'] ?? 94),
            'span' => $span,
            'from' => $bars === [] ? 1 : $bars[0]['n'],
            'to' => $bars === [] ? 1 : $bars[count($bars) - 1]['n'],
            'bars' => $bars,
            'parts' => self::lanes($song, $span),
        ];
    }

    /**
     * A sample take of each part's lines: [left, width, y, sung, on] for each note, `sung` where the voice was and `on`
     * whether that's the note.
     *
     * @param  array<string, list<array{at: float, len: float, midi: int, word: string, left: float, width: float, y: float}>>  $parts
     * @return array<string, array{hit: int, all: int, notes: list<array{left: float, width: float, y: float, sung: float, on: bool}>}>
     */
    public static function take(array $parts): array
    {
        $takes = [];
        foreach ($parts as $key => $notes) {
            $sung = [];
            foreach ($notes as $i => $note) {
                $off = self::MISSES[$i] ?? 0.0;
                // Off the note, the voice sits on the other side when there's no room above or below.
                $at = $note['y'] + $off;
                if ($at > 1.0 || $at < 0.0) {
                    $at = $note['y'] - $off;
                }
                $sung[] = ['left' => $note['left'], 'width' => $note['width'], 'y' => $note['y'], 'sung' => round($at, 3), 'on' => $off === 0.0];
            }
            $takes[$key] = ['hit' => count(array_filter($sung, static fn (array $n): bool => $n['on'])), 'all' => count($sung), 'notes' => $sung];
        }

        return $takes;
    }

    /**
     * Amazing Grace's first line, soprano to bass.
     *
     * @return array{tempo: int, span: float, parts: array<string, list<array{at: float, len: float, midi: int, word: string, left: float, width: float, y: float}>>}
     */
    public static function grace(): array
    {
        $song = self::json(rtrim((string) config('app.web_path'), '/').'/templates/amazing-grace.json');
        $span = self::firstLines($song, 1, 12.0);

        return ['tempo' => (int) ($song['tempo'] ?? 80), 'span' => $span, 'parts' => self::lanes($song, $span)];
    }

    /**
     * Each lesson's link by its name: its unit's page, at the lesson's place on it.
     *
     * @return array<string, string>
     */
    public static function lessons(string $course): array
    {
        $links = [];
        foreach (Lessons::pages($course) as $page) {
            foreach ((array) ($page['lessons'] ?? []) as $i => $lesson) {
                if (is_array($lesson) && is_string($lesson['name'] ?? null)) {
                    $links[$lesson['name']] = $page['url'].'#lesson-'.((int) $i + 1);
                }
            }
        }

        return $links;
    }

    /**
     * The course's unit pages (one a unit: Lessons::PER_UNIT), in order.
     *
     * @return list<array{url: string, name: string, title: string, unitNumber: int}>
     */
    public static function unitPages(string $course): array
    {
        $pages = [];
        foreach (Lessons::pages($course) as $page) {
            if (($page['kind'] ?? '') === 'unit') {
                $pages[] = ['url' => (string) $page['url'], 'name' => (string) $page['name'], 'title' => (string) $page['title'], 'unitNumber' => (int) $page['unitNumber']];
            }
        }

        return $pages;
    }

    /** A unit's rehearsal mark: A, B, C... */
    public static function mark(int $index): string
    {
        return chr(65 + $index % 26);
    }

    /**
     * The four parts' notes that start before `$span`, each placed in its lane.
     *
     * @param  array<array-key, mixed>  $song
     * @return array<string, list<array{at: float, len: float, midi: int, word: string, left: float, width: float, y: float}>>
     */
    private static function lanes(array $song, float $span): array
    {
        $lanes = array_fill_keys(self::PARTS, []);
        foreach ((array) ($song['parts'] ?? []) as $part) {
            $key = is_array($part) ? ($part['key'] ?? null) : null;
            if (! is_string($key) || ! array_key_exists($key, $lanes)) {
                continue;
            }
            $notes = [];
            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                if (is_array($note) && (float) ($note['beat'] ?? 0) < $span) {
                    $notes[] = $note;
                }
            }
            usort($notes, static fn (array $a, array $b): int => (float) $a['beat'] <=> (float) $b['beat']);
            $pitches = array_map(static fn (array $n): int => (int) ($n['pitch'] ?? 60), $notes);
            $low = $pitches === [] ? 0 : min($pitches);
            $high = $pitches === [] ? 0 : max($pitches);
            foreach ($notes as $note) {
                $at = (float) $note['beat'];
                $len = min((float) ($note['length'] ?? 0), $span - $at);
                $midi = (int) ($note['pitch'] ?? 60);
                $lanes[$key][] = [
                    'at' => $at,
                    'len' => $len,
                    'midi' => $midi,
                    'word' => trim((string) ($note['lyric'] ?? ''), ' -,.;:!?'),
                    'left' => round($at / $span * 100, 3),
                    'width' => round($len / $span * 100, 3),
                    'y' => $high > $low ? round(($midi - $low) / ($high - $low), 3) : 0.5,
                ];
            }
        }

        return $lanes;
    }

    /**
     * Where the song's first `$count` lines end, in beats: its first section's phrases.
     *
     * @param  array<array-key, mixed>  $song
     */
    private static function firstLines(array $song, int $count, float $fallback): float
    {
        $phrases = (array) (((array) ($song['sections'][0] ?? []))['phrases'] ?? []);
        $last = $phrases[$count - 1] ?? null;

        return is_array($last) && is_numeric($last[1] ?? null) ? (float) $last[1] : $fallback;
    }

    /**
     * The bar lines before `$span`, each with its bar's number: the first after the upbeat is bar 1.
     *
     * @param  array<array-key, mixed>  $song
     * @return list<array{n: int, left: float}>
     */
    private static function bars(array $song, float $span): array
    {
        [$top, $bottom] = array_map('intval', explode('/', (string) ($song['timeSignature'] ?? '4/4')) + [4, 4]);
        // A beat is a quarter (an 8 time's beat a dotted quarter: three eighths).
        $perBar = $bottom === 8 ? max(1, intdiv($top, 3)) : max(1, $top);
        $pickup = (float) ($song['pickupBeats'] ?? 0);
        $bars = [];
        for ($n = 1, $at = $pickup; $at < $span; $n++, $at += $perBar) {
            $bars[] = ['n' => $n, 'left' => round($at / $span * 100, 3)];
        }

        return $bars;
    }

    /** The template the course sings (its course file's `parts.template`). */
    private static function songSlug(): string
    {
        $course = self::json(rtrim((string) config('app.web_path'), '/').'/course/plus/choir-part/course.json');
        $slug = $course['parts']['template'] ?? null;

        return is_string($slug) && preg_match('/^[a-z0-9-]+$/', $slug) === 1 ? $slug : self::SONG;
    }

    /** @return array<array-key, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
