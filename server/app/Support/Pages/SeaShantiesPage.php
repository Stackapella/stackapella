<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Support\Templates\Catalogue;

/**
 * What the Sea shanties and work songs course page (site/courses/sea-shanties-and-work-songs, Hillel's design of
 * 2026-11-18) shows beyond its words: the hero card's Haul Away, Joe exchange from its song template, the units with
 * their voices and every lesson a link to its page, the songbook's years, and the sample take. The words are
 * lang/en/site.php courses.pages.sea-shanties-and-work-songs.
 */
final class SeaShantiesPage
{
    private const SLUG = 'sea-shanties-and-work-songs';

    /** The chorus's first exchange: the call from beat 0, the crew's answer from ANSWER_AT to SPAN (the template's phrases). */
    public const ANSWER_AT = 9;

    public const SPAN = 16;

    /** The lane's lowest and highest notes (the tune's C#4 and E5 here), so a bar's height follows its pitch. */
    private const LOW = 61;

    private const HIGH = 76;

    /**
     * Each unit's voices, the shantyman's first then the crew's three, and whose is yours: you answer in the crew, pull
     * with it, lead it, then are every voice of it (the course's units).
     */
    private const VOICES = [
        ['lead', 'you', 'crew', 'crew'],
        ['lead', 'you', 'crew', 'crew'],
        ['youlead', 'crew', 'crew', 'crew'],
        ['youlead', 'you', 'you', 'you'],
    ];

    /** The course's songs (its `songs`, in order) that are library songs, by their template's name. */
    private const SONGS = ['drunken-sailor', 'haul-away-joe', 'blow-the-man-down', 'leave-her-johnny'];

    /** The jobs a shanty did, each by the lesson that teaches it: sweating up, stamp and go, the long hoist, the capstan. */
    private const JOBS = ['joe' => 'sh-1-joe', 'stamp' => 'sh-2-stamp', 'hoist' => 'sh-2-hoist', 'capstan' => 'sh-2-capstan'];

    /** The sample take's notes off pitch (of 24): 21 of 24 on, the course's own example of a result. */
    private const MISSED = [5, 13, 19];

    /**
     * Haul Away, Joe's chorus as the course's first lesson of it sings it (sh-1-challenge: the shantyman calls, you
     * answer), from the song's template (database/templates/haul-away-joe.json): the tune's notes, each with its place
     * on the lane, the call's and the answer's words joined from their syllables, and the crew's four parts under the
     * answer for the sound. A missing template gives an empty card, never an error.
     *
     * @return array{song: string, tempo: int, span: int, answerAt: float, call: string, answer: array{0: string, 1: string}, tune: list<array{at: float, len: float, midi: int, left: float, width: float, height: float, answer: bool}>, crew: list<array{at: float, len: float, midi: int}>}
     */
    public static function exchange(): array
    {
        $song = self::json(base_path('database/templates/haul-away-joe.json'));
        $parts = array_values(array_filter((array) ($song['parts'] ?? []), 'is_array'));
        $tune = [];
        $crew = [];
        $callWords = [];
        $answerWords = [];

        foreach ($parts as $index => $part) {
            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                if (! is_array($note)) {
                    continue;
                }
                $at = (float) ($note['beat'] ?? 0);
                $len = (float) ($note['length'] ?? 0);
                $midi = (int) ($note['pitch'] ?? 0);
                if ($at >= self::SPAN || $midi <= 0) {
                    continue;
                }
                $answer = $at >= self::ANSWER_AT;
                // The crew's answer in all four parts (the tune among them); the call is the shantyman's, the tune alone.
                if ($answer && $index > 0) {
                    $crew[] = ['at' => $at, 'len' => $len, 'midi' => $midi];
                }
                if ($index !== 0) {
                    continue;
                }
                $tune[] = [
                    'at' => $at,
                    'len' => $len,
                    'midi' => $midi,
                    'left' => round($at / self::SPAN * 100, 3),
                    'width' => round($len / self::SPAN * 100, 3),
                    'height' => round(30 + 70 * (min(max($midi, self::LOW), self::HIGH) - self::LOW) / (self::HIGH - self::LOW), 1),
                    'answer' => $answer,
                ];
                if (is_string($note['lyric'] ?? null) && $note['lyric'] !== '') {
                    if ($answer) {
                        $answerWords[] = $note['lyric'];
                    } else {
                        $callWords[] = $note['lyric'];
                    }
                }
            }
        }

        $answer = self::words($answerWords);
        $last = (string) strrchr(' '.$answer, ' ');

        return [
            'song' => (string) ($song['title'] ?? ''),
            'tempo' => (int) ($song['tempo'] ?? 90),
            'span' => self::SPAN,
            'answerAt' => round(self::ANSWER_AT / self::SPAN * 100, 3),
            'call' => self::words($callWords),
            // The answer, its last word apart: the pull lands on it.
            'answer' => [rtrim(substr($answer, 0, strlen($answer) - strlen($last) + 1)), ltrim($last)],
            'tune' => $tune,
            'crew' => $crew,
        ];
    }

    /**
     * Each unit's title, what it gives you (its `learn` line, without the unit's own name before the colon), its voices
     * and its lessons: the name, the line under it (its page's search title, without the lesson's own name before the
     * colon), its address, whether it's a song (a unit's closing challenge, folded into the page before it) and whether
     * it's where to start. A course without lesson pages lists the names alone.
     *
     * @param  array<string, mixed>  $page  the course's words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int, minutes?: int|null}|null  $outline
     * @return list<array{title: string, outcome: string, voices: list<string>, lessons: list<array{name: string, line: string, url: string|null, song: bool, start: bool}>}>
     */
    public static function units(array $page, ?array $outline): array
    {
        $byUnit = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            $byUnit[(int) $lessonPage['unitNumber']][] = $lessonPage;
        }
        $learn = array_values((array) ($page['learn'] ?? []));
        $units = [];

        foreach (array_values($outline['units'] ?? []) as $u => $unit) {
            $lessons = [];
            foreach ($byUnit[$u + 1] ?? [] as $lessonPage) {
                $group = array_values((array) $lessonPage['lessons']);
                foreach ($group as $i => $lesson) {
                    $song = (bool) ($lesson['challenge'] ?? false);
                    $lessons[] = [
                        'name' => (string) $lesson['name'],
                        'line' => $song ? '' : self::after((string) $lesson['name'], (string) $lessonPage['title']),
                        'url' => $lessonPage['url'].(count($group) > 1 ? '#lesson-'.($i + 1) : ''),
                        'song' => $song,
                        'start' => $u === 0 && $lessons === [],
                    ];
                }
            }
            if ($lessons === []) {
                $count = count($unit['lessons']);
                foreach ($unit['lessons'] as $i => $name) {
                    $lessons[] = ['name' => $name, 'line' => '', 'url' => null, 'song' => $i === $count - 1, 'start' => $u === 0 && $i === 0];
                }
            }
            $units[] = [
                'title' => $unit['title'],
                'outcome' => self::sentence(self::after($unit['title'], (string) ($learn[$u] ?? ''))),
                'voices' => self::VOICES[$u] ?? self::VOICES[0],
                'lessons' => $lessons,
            ];
        }

        return $units;
    }

    /**
     * The work and the old books: the lessons' own facts from the shanty collections (each lesson's `fact`, cleared by the
     * courses lead and shown on its lesson page), numbered against one Sources list in the order the page cites them.
     *
     * - work: the four jobs of unit 2 (and Haul Away, Joe's sweating up), each with its lesson's name, its own headline
     *   (what the job was, as the lesson teaches it), its fact and the mark for its pull (`marks` in the words);
     * - pull: On the pull's headline and Conan Doyle's account of the pull on the beat;
     * - books: the rest of the facts by the year they tell of (the year in the fact, else its source's), oldest first;
     * - sources: [label, url] each, numbered in that order.
     *
     * @return array{work: list<array{key: string, name: string, headline: string, text: string, n: int}>, pull: array{headline: string, text: string, n: int}|null, books: list<array{year: string, facts: list<array{text: string, n: int}>}>, sources: list<array{0: string, 1: string}>}
     */
    public static function history(): array
    {
        $facts = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            foreach ((array) $lessonPage['lessons'] as $lesson) {
                $fact = $lesson['fact'] ?? null;
                if (! is_array($fact) || ! is_string($fact['text'] ?? null)) {
                    continue;
                }
                $facts[(string) $lesson['id']] = [
                    'name' => (string) $lesson['name'],
                    'headline' => (string) ($lesson['teach'][0]['headline'] ?? ''),
                    'text' => $fact['text'],
                    'source' => (string) ($fact['source'] ?? ''),
                    'url' => (string) ($fact['url'] ?? ''),
                ];
            }
        }

        $sources = [];
        $cite = static function (array $fact) use (&$sources): int {
            $key = $fact['source'].'|'.$fact['url'];
            if (! isset($sources[$key])) {
                $sources[$key] = [$fact['source'], $fact['url']];
            }

            return (int) array_search($key, array_keys($sources), true) + 1;
        };

        $pull = null;
        if (isset($facts['sh-2-pull'])) {
            $pull = ['headline' => $facts['sh-2-pull']['headline'], 'text' => $facts['sh-2-pull']['text'], 'n' => $cite($facts['sh-2-pull'])];
        }
        $work = [];
        foreach (self::JOBS as $key => $id) {
            if (isset($facts[$id])) {
                $work[] = ['key' => $key, 'name' => $facts[$id]['name'], 'headline' => $facts[$id]['headline'], 'text' => $facts[$id]['text'], 'n' => $cite($facts[$id])];
            }
        }

        $byYear = [];
        foreach ($facts as $id => $fact) {
            if ($id === 'sh-2-pull' || in_array($id, self::JOBS, true)) {
                continue;
            }
            $year = preg_match('/\b(1[4-9]\d\d)\b/', $fact['text'], $match) === 1 || preg_match('/\b(1[4-9]\d\d)\b/', $fact['source'], $match) === 1 ? $match[1] : '';
            $byYear[$year][] = $fact;
        }
        ksort($byYear, SORT_STRING);
        $books = [];
        foreach ($byYear as $year => $group) {
            $books[] = ['year' => (string) $year, 'facts' => array_map(static fn (array $fact): array => ['text' => $fact['text'], 'n' => $cite($fact)], $group)];
        }

        return ['work' => $work, 'pull' => $pull, 'books' => $books, 'sources' => array_values($sources)];
    }

    /**
     * The first lesson as its page teaches it: its headline, its cards (a title and a line each), the steps you sing and
     * where its page is; null for a course without lesson pages.
     *
     * @return array{name: string, title: string, url: string, headline: string, cards: list<array{title: string, detail: string}>, sing: list<string>}|null
     */
    public static function firstLesson(): ?array
    {
        $first = Lessons::pages(self::SLUG)[0] ?? null;
        $lesson = $first['lessons'][0] ?? null;
        if (! is_array($first) || ! is_array($lesson)) {
            return null;
        }
        $teach = (array) ($lesson['teach'][0] ?? []);
        $cards = [];
        foreach ((array) ($teach['cards'] ?? []) as $card) {
            if (is_array($card)) {
                $cards[] = ['title' => (string) ($card['title'] ?? ''), 'detail' => (string) ($card['detail'] ?? '')];
            }
        }

        return [
            'name' => (string) $lesson['name'],
            'title' => (string) $first['title'],
            'url' => (string) $first['url'],
            'headline' => (string) ($teach['headline'] ?? ''),
            'cards' => $cards,
            'sing' => array_values(array_map('strval', (array) ($lesson['sing'] ?? []))),
        ];
    }

    /**
     * The questions (`faq` in the words), the length one's count filled in from the course's own file (its lessons, units
     * and the catalogue's minutes); without the catalogue's minutes, that question is left out.
     *
     * @param  array<string, mixed>  $sh  the page's `sh` words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int, minutes?: int|null}|null  $outline
     * @return list<array{0: string, 1: string}>
     */
    public static function faq(array $sh, ?array $outline): array
    {
        $minutes = $outline['minutes'] ?? null;
        $faq = [];
        foreach ((array) ($sh['faq'] ?? []) as $qa) {
            [$question, $answer] = [(string) ($qa[0] ?? ''), (string) ($qa[1] ?? '')];
            if (str_contains($answer, ':minutes')) {
                if (! is_int($minutes) || $outline === null) {
                    continue;
                }
                $answer = str_replace([':lessons', ':units', ':minutes'], [(string) $outline['lessons'], (string) count($outline['units']), (string) $minutes], $answer);
            }
            $faq[] = [$question, $answer];
        }

        return $faq;
    }

    /**
     * The songbook: each song's name, the year its source was printed (the first year its credit gives, only where it
     * gives one) and the credit itself with that year left in its own column, every other word of the citation kept; and,
     * for a library song with a reviewed story, its story's lead, and its page while the site shows it.
     *
     * @param  list<array{0: string, 1: string}>  $songs
     * @return list<array{title: string, year: string|null, source: string, lead: string, url: string|null}>
     */
    public static function songbook(array $songs): array
    {
        $book = [];
        // A song's page is linked only while the site shows it (Catalogue::openIndex(), read once and kept a day).
        $open = array_column(app(Catalogue::class)->openIndex(), 'slug');
        foreach ($songs as $song) {
            [$title, $credit] = [(string) ($song[0] ?? ''), (string) ($song[1] ?? '')];
            $year = preg_match('/ \((\d{4})\)/', $credit, $match) === 1 ? $match[1] : null;
            $slug = self::SONGS[count($book)] ?? null;
            $story = $slug !== null ? SongStories::story($slug) : null;
            $book[] = [
                'title' => $title,
                'year' => $year,
                'source' => $year === null ? $credit : (string) preg_replace('/ \('.$year.'\)/', '', $credit, 1),
                'lead' => is_string($story['lead'] ?? null) ? $story['lead'] : '',
                'url' => $story !== null && in_array($slug, $open, true) ? '/songs/'.$slug : null,
            ];
        }

        return $book;
    }

    /**
     * The sample take's 24 notes, true where on pitch.
     *
     * @return list<bool>
     */
    public static function sample(): array
    {
        return array_map(static fn (int $i): bool => ! in_array($i, self::MISSED, true), range(0, 23));
    }

    /** "Sweating up: one short answer" after its own name is "One short answer"; any other line is kept whole. */
    private static function after(string $name, string $line): string
    {
        $prefix = $name.': ';
        if ($name === '' || ! str_starts_with(mb_strtolower($line), mb_strtolower($prefix))) {
            return $line;
        }

        return self::upperFirst(mb_substr($line, mb_strlen($prefix)));
    }

    /** A line as a sentence: its first letter capital and a full stop at its end. */
    private static function sentence(string $line): string
    {
        $line = self::upperFirst(trim($line));

        return $line === '' || str_ends_with($line, '.') ? $line : $line.'.';
    }

    private static function upperFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * Sung syllables as words: "a-" joins the next one, and a note without a word (a slur) adds nothing.
     *
     * @param  list<string>  $syllables
     */
    private static function words(array $syllables): string
    {
        $text = '';
        foreach ($syllables as $syllable) {
            $text .= str_ends_with($syllable, '-') ? substr($syllable, 0, -1) : $syllable.' ';
        }

        return trim($text);
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
