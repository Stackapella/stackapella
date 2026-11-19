<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Str;
use NumberFormatter;

/**
 * The Barbershop course page's figures and counts (site/courses/barbershop, Hillel's playbill design of 2026-11-18), so
 * the view carries no logic of its own.
 *
 * - PHRASE: the course's phrase of Down by the Old Mill Stream, as unit 3 sings it (bb-3-lead's play step, from the
 *   lead's first sung note): [beat, length, semitones from A3, cents], a voice a part. tests/Feature/Site/BarbershopPageTest
 *   keeps it the same as the course.
 * - counts(): the course's lessons and units, as a number and in the page's language's words ("Seventeen", "four").
 * - title(): a title split before its italic end.
 * - wavy(): a rung's line with the wave a wavy voice sings.
 * - seat(): which seat a lesson is about, by the seat's name in its title.
 */
final class BarbershopPage
{
    /** @var array<string, list<array{0: float|int, 1: float|int, 2: int, 3: int}>> */
    public const PHRASE = [
        'tenor' => [[0, .5, 2, 0], [.5, .5, 5, 0], [1, .5, 5, 0], [1.5, 3, 10, 0], [4.5, 3, 7, 0], [7.5, 4, 12, 2]],
        'lead' => [[0, .5, -7, 0], [.5, .5, -2, 0], [1, .5, 2, 0], [1.5, 3, 5, 2], [4.5, 3, 4, 0], [7.5, 4, 3, -31]],
        'baritone' => [[0, .5, 2, 0], [.5, .5, 2, 0], [1, .5, -2, 0], [1.5, 3, 2, -14], [4.5, 3, -2, 0], [7.5, 4, -3, -14]],
        'bass' => [[0, .5, -14, 0], [.5, .5, -14, 0], [1, .5, -14, 0], [1.5, 3, -14, 0], [4.5, 3, -11, 0], [7.5, 4, -7, 0]],
    ];

    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

    /**
     * The course's lessons and units, as numbers and in words.
     *
     * @param  array{units?: list<array{title: string, lessons: list<string>}>, lessons?: int}|null  $outline
     * @return array{lessons: int, units: int, lessonsWords: string, unitsWords: string}
     */
    public static function counts(?array $outline, string $locale): array
    {
        $lessons = (int) ($outline['lessons'] ?? 0);
        $units = count($outline['units'] ?? []);

        return ['lessons' => $lessons, 'units' => $units, 'lessonsWords' => Str::ucfirst(self::spell($lessons, $locale)), 'unitsWords' => self::spell($units, $locale)];
    }

    /** A number in words in the language ("seventeen", "diecisiete"); its digits where that can't be done. */
    public static function spell(int $n, string $locale): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return (string) $n;
        }

        $words = (new NumberFormatter($locale, NumberFormatter::SPELLOUT))->format($n);

        return is_string($words) ? $words : (string) $n;
    }

    public static function roman(int $n): string
    {
        return self::ROMAN[$n - 1] ?? (string) $n;
    }

    /**
     * A title and its italic end: [the start, the end], the end only when the title ends with it.
     *
     * @return array{0: string, 1: string}
     */
    public static function title(string $title, string $em): array
    {
        if ($em === '' || $em === $title || ! str_ends_with($title, $em)) {
            return [$title, ''];
        }

        return [rtrim(substr($title, 0, -strlen($em))), $em];
    }

    /** A rung's line from x 54 to 330 at $y, with nine waves of 5 units in it. */
    public static function wavy(int $y): string
    {
        $path = 'M54 '.$y;
        foreach (range(1, 69) as $i) {
            $path .= ' L'.(54 + $i * 4).' '.round($y + 5 * sin($i * 4 / 276 * M_PI * 18), 1);
        }

        return $path;
    }

    /**
     * The seat a lesson is about (its name a word in the lesson's title), or null.
     *
     * @param  array<string, string>  $names  each seat's name in the page's language, by its key
     */
    public static function seat(string $lesson, array $names): ?string
    {
        foreach ($names as $key => $name) {
            if (preg_match('/\b'.preg_quote(mb_strtolower($name), '/').'\b/u', mb_strtolower($lesson)) === 1) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Each lesson of a unit with its seat: a part's key, '' for a lesson in an act about the seats that names none (the
     * open seat), or null when the act isn't about the seats (two or more of its lessons name one).
     *
     * @param  list<string>  $lessons
     * @param  array<string, string>  $names
     * @return list<array{0: string, 1: string|null}>
     */
    public static function seats(array $lessons, array $names): array
    {
        $found = array_map(static fn (string $lesson): ?string => self::seat($lesson, $names), $lessons);
        $seated = count(array_filter($found, static fn (?string $key): bool => $key !== null)) >= 2;

        return array_map(static fn (string $lesson, ?string $key): array => [$lesson, $seated ? ($key ?? '') : null], $lessons, $found);
    }

    /**
     * The questions with the course's own count of lessons in their answers (":n").
     *
     * @param  array<int, mixed>  $faq
     * @return list<array{0: string, 1: string}>
     */
    public static function faq(array $faq, int $lessons): array
    {
        $out = [];
        foreach ($faq as $qa) {
            if (is_array($qa)) {
                $out[] = [(string) ($qa[0] ?? ''), str_replace(':n', (string) $lessons, (string) ($qa[1] ?? ''))];
            }
        }

        return $out;
    }
}
