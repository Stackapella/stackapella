<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * Singing in another language's own course page (site/courses/other-languages, Hillel's design, 2026-11-18): what its
 * view draws that it reads from elsewhere or measures, so the view stays markup. The four songs' opening lines from
 * their templates (one box a sung syllable, its length the template's own notes), every lesson with its lesson page,
 * and the two demo decks' geometry (Sakura's ka-gi-ri and obasan, and Shchedryk's figure up the speed ladder).
 */
final class OtherLanguagesPage
{
    /** The four languages in the course's order, each unit's: Japanese, Spanish, church Latin, Ukrainian. */
    public const LANGUAGES = ['ja', 'es', 'la', 'uk'];

    /**
     * Each song's opening line: its template, the part that carries the tune and the beats it spans (the template's
     * own phrases: Sakura's first, La Paloma's first, Ave verum's first two, "Ave, ave verum corpus", and Shchedryk's
     * first).
     */
    public const LINES = [
        'ja' => ['template' => 'sakura-sakura', 'part' => 'soprano', 'from' => 0, 'to' => 8],
        'es' => ['template' => 'la-paloma', 'part' => 'soprano', 'from' => 0, 'to' => 10],
        'la' => ['template' => 'ave-verum-corpus', 'part' => 'soprano', 'from' => 0, 'to' => 16],
        'uk' => ['template' => 'shchedryk', 'part' => 'soprano', 'from' => 0, 'to' => 6],
    ];

    /** The outcomes' numerals, as a primer numbers them. */
    public const NUMERALS = ['i', 'ii', 'iii', 'iv', 'v', 'vi'];

    /** The hue of each of the three things a new language asks for: morae, the sinalefa, and the tap (every language's: the red ink). */
    public const ASKS = ['ja', 'es', 'red'];

    /** The hue of each margin note, by the language it's about (love.facts, in order). */
    public const FACTS = ['ja', 'ja', 'ja', 'es', 'la', 'uk'];

    /** Each archive entry's language (a love.names key, in order); church Latin's take its hue, the rest the muted ink. */
    public const ARCHIVE = ['latin', 'it', 'latin', 'en', 'latin', 'latin'];

    /** The hue class for a language key: a course language's own, church Latin's for Latin, the red ink, else the muted ink. */
    public static function hue(string $key): string
    {
        return match ($key) {
            'ja', 'es', 'la', 'uk', 'red' => $key,
            'latin' => 'la',
            default => 'mute',
        };
    }

    /** The course's speed ladder for Shchedryk's figure (unit 4, lesson 2): twice at 60, four times at 80, four at 96. */
    public const LADDER = [60, 60, 80, 80, 80, 80, 96, 96, 96, 96];

    /** A round of the ladder: the figure's three beats and a beat's rest. */
    private const ROUND_BEATS = 4;

    /** The ladder drawing's time axis: x from 16 to 584 across the whole climb. */
    private const LADDER_LEFT = 16;

    private const LADDER_WIDTH = 568;

    /**
     * Each song's opening line as boxes: a box a sung syllable, `len` its beats (its own note and any notes after it
     * on the same vowel, whose starts are `marks`, in beats from the box's start), a rest between two as a gap. The
     * words come from the template (lyric hyphens and punctuation dropped; two words on one note joined by a tie,
     * "la‿Ha") unless `$words[lang]['script']` gives the line in its own script, a box each (Sakura's kana); `hints`
     * gives what's written under each box, "_" for none. A line whose template can't be read is left out.
     *
     * @param  array<array-key, mixed>  $words  lang/en/site.php courses.pages.other-languages.love.lines.rows
     * @return list<array{lang: string, song: string, label: string, beats: float, items: list<array{len: float, text: string|null, hint: string|null, tie: bool, marks: list<float>}>}>
     */
    public static function firstLines(array $words, ?string $templates = null): array
    {
        $templates ??= base_path('database/templates');
        $lines = [];

        foreach (self::LINES as $lang => $line) {
            $row = is_array($words[$lang] ?? null) ? $words[$lang] : [];
            $notes = self::tune($templates.'/'.$line['template'].'.json', $line['part']);
            $syllables = self::syllables($notes, (float) $line['from'], (float) $line['to']);
            if ($syllables === []) {
                continue;
            }

            $script = self::slots($row['script'] ?? null, count($syllables));
            $hints = self::slots($row['hints'] ?? null, count($syllables));
            $items = [];
            $at = $syllables[0]['beat'];
            foreach ($syllables as $i => $syllable) {
                if ($syllable['beat'] - $at > 0.01) {
                    $items[] = ['len' => round($syllable['beat'] - $at, 4), 'text' => null, 'hint' => null, 'tie' => false, 'marks' => []];
                }
                $items[] = [
                    'len' => round($syllable['len'], 4),
                    'text' => $script[$i] ?? $syllable['text'],
                    'hint' => $hints[$i] ?? null,
                    'tie' => $syllable['tie'],
                    'marks' => $syllable['marks'],
                ];
                $at = $syllable['beat'] + $syllable['len'];
            }

            $lines[] = [
                'lang' => $lang,
                'song' => (string) ($row['song'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'beats' => round($at - $syllables[0]['beat'], 4),
                'items' => $items,
            ];
        }

        return $lines;
    }

    /**
     * A template part's notes, [beat, length, lyric|null] each; empty when the file or the part isn't there.
     *
     * @return list<array{0: float, 1: float, 2: string|null}>
     */
    public static function tune(string $path, string $part): array
    {
        $song = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $notes = [];
        foreach (is_array($song) ? (array) ($song['parts'] ?? []) : [] as $p) {
            if (! is_array($p) || ($p['key'] ?? null) !== $part) {
                continue;
            }
            foreach ((array) ($p['guide']['notes'] ?? []) as $note) {
                if (is_array($note) && is_numeric($note['beat'] ?? null) && is_numeric($note['length'] ?? null)) {
                    $notes[] = [(float) $note['beat'], (float) $note['length'], is_string($note['lyric'] ?? null) ? $note['lyric'] : null];
                }
            }
        }

        return $notes;
    }

    /**
     * The sung syllables from `$from` up to `$to`: a note with a lyric starts one, a note without carries the one
     * before on to a new pitch (a mark where it starts).
     *
     * @param  list<array{0: float, 1: float, 2: string|null}>  $notes
     * @return list<array{beat: float, len: float, text: string, tie: bool, marks: list<float>}>
     */
    public static function syllables(array $notes, float $from, float $to): array
    {
        $syllables = [];
        $open = null; // the syllable being sung, until the next lyric starts another
        foreach ($notes as [$beat, $length, $lyric]) {
            if ($beat < $from - 0.001 || $beat >= $to - 0.001) {
                continue;
            }
            if ($lyric === null) {
                if ($open !== null) {
                    $open['marks'][] = round($beat - $open['beat'], 4);
                    $open['len'] = $beat + $length - $open['beat'];
                }

                continue;
            }
            if ($open !== null) {
                $syllables[] = $open;
            }
            $words = preg_split('/\s+/u', trim($lyric)) ?: [];
            $words = array_values(array_filter(array_map(self::clean(...), $words), static fn (string $w): bool => $w !== ''));
            $open = ['beat' => $beat, 'len' => $length, 'text' => implode('‿', $words), 'tie' => count($words) > 1, 'marks' => []];
        }
        if ($open !== null) {
            $syllables[] = $open;
        }

        return $syllables;
    }

    /** A lyric without its hyphen and punctuation: "¡Vál-" is "Vál", "Dios!" is "Dios", "рик," is "рик". */
    public static function clean(string $lyric): string
    {
        return trim((string) preg_replace('/[\-,.;:!?¡¿"“”«»]/u', '', $lyric));
    }

    /**
     * A line's words split a box each ("_" for none), only when there are exactly as many as boxes.
     *
     * @return list<string|null>
     */
    private static function slots(mixed $text, int $count): array
    {
        if (! is_string($text) || trim($text) === '') {
            return [];
        }
        $slots = preg_split('/\s+/u', trim($text)) ?: [];
        if (count($slots) !== $count) {
            return [];
        }

        return array_map(static fn (string $s): ?string => $s === '_' ? null : $s, $slots);
    }

    /**
     * Every lesson by unit, each with its number on the page and its lesson page (null until the course is open and
     * has them); `$outline` is SitePages::courseOutline's, for the units' and lessons' names in the page's language.
     *
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int}  $outline
     * @return list<array{title: string, lang: string, lessons: list<array{n: string, name: string, url: string|null}>}>
     */
    public static function units(array $outline, ?string $path = null): array
    {
        $path ??= dirname(base_path()).'/web/course/plus/other-languages/course.json';
        $file = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $pages = [];
        foreach (Lessons::pages('other-languages') as $page) {
            foreach ((array) ($page['ids'] ?? []) as $id) {
                $pages[(string) $id] ??= (string) $page['url'];
            }
        }

        $units = [];
        $number = 0;
        foreach ($outline['units'] as $u => $unit) {
            $ids = is_array($file) && is_array($file['units'][$u]['lessons'] ?? null) ? array_values(array_filter($file['units'][$u]['lessons'], 'is_array')) : [];
            $rows = [];
            foreach ($unit['lessons'] as $l => $name) {
                $id = (string) ($ids[$l]['id'] ?? '');
                $rows[] = ['n' => str_pad((string) ++$number, 2, '0', STR_PAD_LEFT), 'name' => $name, 'url' => $pages[$id] ?? null];
            }
            $units[] = ['title' => $unit['title'], 'lang' => self::LANGUAGES[$u] ?? 'ja', 'lessons' => $rows];
        }

        return $units;
    }

    /**
     * Whether every lesson on the page links to its lesson page, so the shared list below the page can go.
     *
     * @param  list<array{lessons: list<array{url: string|null}>}>  $units
     */
    public static function linksEveryLesson(array $units): bool
    {
        $rows = [];
        foreach ($units as $unit) {
            foreach ($unit['lessons'] as $lesson) {
                $rows[] = $lesson['url'];
            }
        }

        return $rows !== [] && ! in_array(null, $rows, true);
    }

    /**
     * Shchedryk's figure up the ladder, drawn on one time axis: each round's four notes ([x, width, MIDI]) from the
     * template's own figure, a round's x where it starts, and each new tempo's label.
     *
     * @return array{rounds: list<array{x: float, notes: list<array{x: float, w: float, midi: int}>}>, tempos: list<array{x: float, bpm: int}>}
     */
    public static function ladder(?string $templates = null): array
    {
        $figure = self::figure($templates);
        $seconds = array_sum(array_map(static fn (int $bpm): float => self::ROUND_BEATS * 60.0 / $bpm, self::LADDER));
        $scale = self::LADDER_WIDTH / $seconds;
        $rounds = [];
        $tempos = [];
        $t = 0.0;
        foreach (self::LADDER as $k => $bpm) {
            $beat = 60 / $bpm;
            $notes = [];
            foreach ($figure as [$at, $length, $midi]) {
                $notes[] = ['x' => round(self::LADDER_LEFT + ($t + $at * $beat) * $scale, 2), 'w' => round(max(2, $length * $beat * $scale - 3), 2), 'midi' => $midi];
            }
            $x = round(self::LADDER_LEFT + $t * $scale, 2);
            $rounds[] = ['x' => $x, 'notes' => $notes];
            if ($k === 0 || self::LADDER[$k - 1] !== $bpm) {
                $tempos[] = ['x' => $x, 'bpm' => $bpm];
            }
            $t += self::ROUND_BEATS * $beat;
        }

        return ['rounds' => $rounds, 'tempos' => $tempos];
    }

    /**
     * Shchedryk's four-note figure from its template, [beat, beats, MIDI] each (B flat 4, A 4, B flat 4, G 4: long,
     * short, short, long), and its four syllables; the course's figure as a fallback when the template can't be read.
     *
     * @return list<array{0: float, 1: float, 2: int}>
     */
    public static function figure(?string $templates = null): array
    {
        $templates ??= base_path('database/templates');
        $song = is_file($templates.'/shchedryk.json') ? json_decode((string) file_get_contents($templates.'/shchedryk.json'), true) : null;
        $figure = [];
        foreach (is_array($song) ? (array) ($song['parts'] ?? []) : [] as $p) {
            if (is_array($p) && ($p['key'] ?? null) === 'soprano') {
                foreach (array_slice((array) ($p['guide']['notes'] ?? []), 0, 4) as $note) {
                    if (is_array($note) && is_numeric($note['beat'] ?? null) && is_numeric($note['length'] ?? null) && is_numeric($note['pitch'] ?? null)) {
                        $figure[] = [(float) $note['beat'], (float) $note['length'], (int) $note['pitch']];
                    }
                }
            }
        }

        return count($figure) === 4 ? $figure : [[0.0, 1.0, 70], [1.0, 0.5, 69], [1.5, 0.5, 70], [2.0, 1.0, 67]];
    }

    /**
     * The figure for the ladder's script: [MIDI, beat, beats] each.
     *
     * @return list<array{0: int, 1: float, 2: float}>
     */
    public static function figureNotes(?string $templates = null): array
    {
        return array_map(static fn (array $n): array => [$n[2], $n[0], $n[1]], self::figure($templates));
    }

    /**
     * The figure's four sung syllables in Cyrillic, as the template writes them ("Щед-", "рик,", "щед-", "рик,").
     *
     * @return list<string>
     */
    public static function figureWords(?string $templates = null): array
    {
        $templates ??= base_path('database/templates');

        return array_map(static fn (array $n): string => (string) $n[2], array_slice(self::tune($templates.'/shchedryk.json', 'soprano'), 0, 4));
    }

    /** The y of a MIDI note on the ladder drawing: G 4 on the true key's line at 64, ten units a semitone above it. */
    public static function ladderY(int $midi): int
    {
        return 64 - ($midi - 67) * 10;
    }

    /**
     * One of the page's marked lines as pieces: *words* in italic (`em`), [words] struck through (`struck`), the rest
     * plain. Text only, escaped by the view.
     *
     * @return list<array{text: string, kind: string}>
     */
    public static function marked(string $line): array
    {
        $pieces = [];
        foreach (preg_split('/(\*[^*]+\*|\[[^\]]+\])/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            $kind = match ($piece[0]) {
                '*' => 'em',
                '[' => 'struck',
                default => 'plain',
            };
            $pieces[] = ['text' => $kind === 'plain' ? $piece : mb_substr($piece, 1, -1), 'kind' => $kind];
        }

        return $pieces;
    }

    /**
     * A heading split at its last comma, the part after it set in italic ("Four songs, four languages," and "harder
     * each time"); the whole as the first part when it has none.
     *
     * @return array{0: string, 1: string}
     */
    public static function tail(string $title): array
    {
        $at = mb_strrpos($title, ', ');

        return $at === false ? [$title, ''] : [mb_substr($title, 0, $at + 1), mb_substr($title, $at + 2)];
    }

    /**
     * A heading's four-figure years apart from its words, for the red ink: [text, isYear] each.
     *
     * @return list<array{0: string, 1: bool}>
     */
    public static function years(string $title): array
    {
        return array_map(
            static fn (string $p): array => [$p, preg_match('/^\d{4}$/', $p) === 1],
            preg_split('/(\d{4})/', $title, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [],
        );
    }

    /**
     * The glide drawn small ("lay: eh, ee"): the word and its two vowels; empty when it doesn't read that way.
     *
     * @return array{word: string, from: string, to: string}|null
     */
    public static function glide(string $line): ?array
    {
        return preg_match('/^(.+?):\s*(.+?),\s*(.+)$/u', $line, $m) === 1 ? ['word' => $m[1], 'from' => $m[2], 'to' => $m[3]] : null;
    }

    /**
     * Two lines of words a box each, paired ("Do mi ne" over "DOE MEE NAY"): [top, bottom] each, as many as the bottom
     * line has.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function pairs(string $top, string $bottom): array
    {
        $over = preg_split('/\s+/u', trim($top)) ?: [];
        $pairs = [];
        foreach (preg_split('/\s+/u', trim($bottom)) ?: [] as $i => $word) {
            $pairs[] = [$over[$i] ?? '', $word];
        }

        return $pairs;
    }

    /** The mora deck: 3.25 beats across 70 to 620 (169 units a beat). */
    public static function beatX(float $beat): int
    {
        return (int) round(70 + $beat * 169);
    }

    /** Obasan and obāsan: five half-beat slots across 70 to 620. */
    public static function slotX(int $slot): int
    {
        return 70 + $slot * 110;
    }
}
