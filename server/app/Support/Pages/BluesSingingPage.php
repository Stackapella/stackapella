<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Str;

/**
 * What Blues singing's own page (site/courses/blues-singing, Hillel's design of 2026-11-18) draws, so its view stays
 * markup: Shorty George Blues' twelve bars as the course teaches them, the sample take, and its words cut where the
 * design sets a part apart (the last sentence in italics, a unit's name over its line, a song's year).
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
