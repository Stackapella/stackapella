<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * What the Scat singing course page (site/courses/scat, after Hillel's design of 2026-11-18) shows beyond its words: the
 * demo's line and where each note sits in each feel, the twelve-bar chart's chords by their job, the record sleeves'
 * catalogue numbers, and the four record sleeves, every lesson a link to its page (Lessons::pages). The words are
 * lang/en/site.php courses.pages.scat; the sounds are js/public/scat.js's, reading the same numbers from the page.
 */
final class ScatPage
{
    /**
     * The demo's line: lesson 2's own (sc-1-swing, "Long, short", its last sing step in web/course/plus/scat/course.json,
     * "Now on two notes, swung, twice round, landing on doo"), [beat, length in beats, semitones above do], swung as the
     * course writes it. tests/Feature/Site/ScatPageTest.php keeps it the same as the lesson's.
     */
    public const LINE = [
        [0, 0.6667, 0], [0.6667, 0.3333, 2], [1, 0.6667, 0], [1.6667, 0.3333, 2], [2, 0.6667, 0], [2.6667, 0.3333, 2], [3, 0.6667, 0], [3.6667, 0.3333, 2],
        [4, 0.6667, 0], [4.6667, 0.3333, 2], [5, 0.6667, 0], [5.6667, 0.3333, 2], [6, 0.6667, 0], [6.6667, 0.3333, 2], [7, 0.6667, 0], [7.6667, 0.3333, 2],
        [8, 2, 0],
    ];

    /** Do, as MIDI: the line in C, over C7. */
    public const DO = 60;

    /** Beats a minute: a beat of 500 ms, so the swung "and" lands 333 ms in, two thirds of the way through the beat. */
    public const TEMPO = 120;

    /** The soloist's lean: each note on the beat this much late, in beats (33 ms at 120; our choice, the page says so). */
    public const LATE = 0.066;

    /** The feels the demo plays, in their order on the page. */
    public const FEELS = ['straight', 'swung', 'soloist'];

    /** The twelve bars' jobs: I is home, IV away, V the turn back. */
    public const JOBS = ['home', 'home', 'home', 'home', 'away', 'away', 'home', 'home', 'turn', 'away', 'home', 'turn'];

    /** A job's numeral and colour on the page, for the story's records too (by the job they wear). */
    public const STORY_JOBS = ['home', 'away', 'turn', 'home', 'away'];

    private const SLUG = 'scat';

    /**
     * Where a moment of the swung line falls in a feel, in beats: straight puts the "and" half way through the beat,
     * swung keeps it at two thirds, and the soloist sits each beat a touch late and meets the band on the "and".
     */
    public static function at(float $beat, string $feel): float
    {
        $whole = floor($beat + 0.001);
        $part = $beat - $whole;
        $onTheAnd = abs($part - 0.6667) < 0.01;

        return round(match ($feel) {
            'straight' => $onTheAnd ? $whole + 0.5 : $beat,
            'soloist' => $onTheAnd ? $beat : $beat + self::LATE,
            default => $beat,
        }, 4);
    }

    /**
     * Each note of the line in each feel: [start, length] in beats, the numbers that place the tiles and time the sound.
     *
     * @return array<string, list<array{0: float, 1: float}>>
     */
    public static function feels(): array
    {
        $feels = [];
        foreach (self::FEELS as $feel) {
            $feels[$feel] = array_map(static function (array $note) use ($feel): array {
                $start = self::at((float) $note[0], $feel);

                return [$start, round(self::at((float) $note[0] + (float) $note[1], $feel) - $start, 4)];
            }, self::LINE);
        }

        return $feels;
    }

    /**
     * The line's notes as MIDI.
     *
     * @return list<int>
     */
    public static function midi(): array
    {
        return array_map(static fn (array $note): int => self::DO + (int) $note[2], self::LINE);
    }

    /**
     * The lane's tiles: the line's first bar (the second is the same, and the landing falls on its first beat), each
     * placed per feel as a share of the bar (CSS custom properties, in percent), and its height by its pitch.
     *
     * @return list<array{index: int, short: bool, rise: int, style: string}>
     */
    public static function tiles(): array
    {
        $feels = self::feels();
        $steps = array_map(static fn (array $note): int => (int) $note[2], self::LINE);
        $top = max(1, max($steps));
        $tiles = [];
        foreach (self::LINE as $i => $note) {
            if ((float) $note[0] >= 4) {
                break;
            }
            $style = '';
            foreach (self::FEELS as $feel) {
                [$start, $length] = $feels[$feel][$i];
                $style .= '--x-'.$feel.': '.round($start / 4 * 100, 3).'; --w-'.$feel.': '.round($length / 4 * 100, 3).'; ';
            }
            $tiles[] = ['index' => $i, 'short' => (float) $note[1] < 0.5, 'rise' => (int) round((int) $note[2] / $top * 100), 'style' => trim($style)];
        }

        return $tiles;
    }

    /**
     * The counts under the lane: each beat's number where the beat falls, and its "and" where each feel puts it.
     *
     * @return list<array{beat: int, and: bool, style: string}>
     */
    public static function counts(): array
    {
        $counts = [];
        foreach (range(0, 3) as $beat) {
            $counts[] = ['beat' => $beat, 'and' => false, 'style' => '--x-straight: '.($beat * 25).'; --x-swung: '.($beat * 25).'; --x-soloist: '.($beat * 25).';'];
            $style = '';
            foreach (self::FEELS as $feel) {
                $style .= '--x-'.$feel.': '.round(self::at($beat + 0.6667, $feel) / 4 * 100, 3).'; ';
            }
            $counts[] = ['beat' => $beat, 'and' => true, 'style' => trim($style)];
        }

        return $counts;
    }

    /** A record's catalogue number, two figures at least: SC-01, SC-12. */
    public static function catalogue(string $words, int $n): string
    {
        return str_replace(':n', str_pad((string) $n, 2, '0', STR_PAD_LEFT), $words);
    }

    /**
     * A headline with its last character set apart (the design's coloured full stop), when it is one.
     *
     * @return array{0: string, 1: string}
     */
    public static function stop(string $text): array
    {
        $last = mb_substr($text, -1);

        return in_array($last, ['.', '!', '?'], true) ? [mb_substr($text, 0, -1), $last] : [$text, ''];
    }

    /**
     * The four record sleeves: each unit's title, its line, and its lessons as tracks, each a link to its page (a
     * challenge to its place on the page it's folded into); a course without lesson pages lists the names alone.
     *
     * @param  array<string, mixed>  $page  the course's words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}|null  $outline
     * @return list<array{title: string, say: string, job: string, lessons: list<array{name: string, url: string|null}>}>
     */
    public static function units(array $page, ?array $outline): array
    {
        $byUnit = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            $byUnit[(int) $lessonPage['unitNumber']][] = $lessonPage;
        }
        $says = array_values((array) ($page['units_say'] ?? []));
        $jobs = ['home', 'away', 'turn', 'ink'];
        $units = [];

        foreach (array_values($outline['units'] ?? []) as $u => $unit) {
            $lessons = [];
            foreach ($byUnit[$u + 1] ?? [] as $lessonPage) {
                $group = array_values((array) $lessonPage['lessons']);
                foreach ($group as $i => $lesson) {
                    $lessons[] = ['name' => (string) $lesson['name'], 'url' => $lessonPage['url'].(count($group) > 1 ? '#lesson-'.($i + 1) : '')];
                }
            }
            if ($lessons === []) {
                $lessons = array_map(static fn (string $name): array => ['name' => $name, 'url' => null], $unit['lessons']);
            }
            $units[] = ['title' => $unit['title'], 'say' => (string) ($says[$u] ?? ''), 'job' => $jobs[$u % 4], 'lessons' => $lessons];
        }

        return $units;
    }
}
