<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;
use Illuminate\Support\HtmlString;

/**
 * The Musical theatre course page's pieces (site/courses/musical-theatre, Hillel's design of 2026-11-18): a Savoy
 * programme, with the demo's notes and the running order taken from the course itself.
 *
 * - patter(): the Major-General's first fourteen syllables, the notes and syllables of the course's own lesson (unit 2's
 *   Two notes, many words, mt-2-two: its whole line, the last play step), in scale steps from the singer's note. They're
 *   placed where the Chappell vocal score has them (No. 13, p. 61): the singer's note on B flat, printed in the treble
 *   clef and sung, by a baritone, an octave lower, so B flat 3 (MIDI 58). Each is [syllable, midi, high], `high` for the
 *   upper of the two notes.
 * - acts(): every unit as an act of the running order, with every lesson linked to the lesson page it's on (a unit's
 *   song, its challenge, marked as the act's number) or none when the course has no lesson pages.
 *
 * A file that's missing gives an empty demo and lessons without links, never an error.
 */
final class MusicalTheatrePage
{
    public const COURSE = 'musical-theatre';

    /** The lesson the demo's notes come from, and how many syllables it plays. */
    public const LESSON = 'mt-2-two';

    public const SYLLABLES = 14;

    /** The singer's note in the score: B flat, sung an octave below the treble clef it's printed in. */
    public const HOME = 58;

    /** The acts' numbers, as a playbill sets them. */
    private const NUMERALS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

    /**
     * @return list<array{0: string, 1: int, 2: bool}>
     */
    public static function patter(): array
    {
        $line = [];
        foreach ((array) (self::lesson(self::LESSON)['steps'] ?? []) as $step) {
            if (is_array($step) && ($step['kind'] ?? '') === 'play' && is_array($step['exercise']['notes'] ?? null)) {
                $line = array_values(array_filter($step['exercise']['notes'], 'is_array'));
            }
        }
        $notes = array_slice($line, 0, self::SYLLABLES);
        $top = $notes === [] ? 0 : max(array_map(static fn (array $note): int => (int) ($note['step'] ?? 0), $notes));

        return array_map(static fn (array $note): array => [
            (string) ($note['lyric'] ?? ''),
            self::HOME + (int) ($note['step'] ?? 0),
            (int) ($note['step'] ?? 0) === $top,
        ], $notes);
    }

    /**
     * Every unit as an act, its lessons each linked to its lesson page; `$outline` is SitePages::courseOutline's, for the
     * units' and lessons' names in the page's language.
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}  $outline
     * @return list<array{n: int, numeral: string, title: string, lessons: list<array{name: string, url: string|null, song: bool}>}>
     */
    public static function acts(array $outline): array
    {
        $file = self::json(self::web('course/plus/'.self::COURSE.'/course.json'));
        $pages = [];
        foreach (Lessons::pages(self::COURSE) as $page) {
            foreach ((array) ($page['ids'] ?? []) as $id) {
                $pages[(string) $id] ??= (string) $page['url'];
            }
        }

        $acts = [];
        foreach ($outline['units'] as $u => $unit) {
            $lessons = array_values(array_filter((array) (is_array($file['units'][$u] ?? null) ? ($file['units'][$u]['lessons'] ?? []) : []), 'is_array'));
            $rows = [];
            foreach ($unit['lessons'] as $l => $name) {
                $lesson = $lessons[$l] ?? [];
                $rows[] = ['name' => $name, 'url' => $pages[(string) ($lesson['id'] ?? '')] ?? null, 'song' => (bool) ($lesson['challenge'] ?? false)];
            }
            $acts[] = ['n' => $u + 1, 'numeral' => self::NUMERALS[$u] ?? (string) ($u + 1), 'title' => $unit['title'], 'lessons' => $rows];
        }

        return $acts;
    }

    /** One of the page's headings as HTML, its *words* in italic. Escaped first. */
    public static function marked(string $line): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e($line)));
    }

    /** @return array<array-key, mixed> */
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
