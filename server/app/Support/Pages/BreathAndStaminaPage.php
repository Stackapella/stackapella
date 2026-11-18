<?php

declare(strict_types=1);

namespace App\Support\Pages;

use NumberFormatter;

/**
 * What the Breath and stamina course page (site/courses/breath-and-stamina) shows beyond its words: the hero's held note
 * on its 15-second scale, the map's count line, and the lesson map, each unit's lessons as links to their pages
 * (Lessons::pages), a challenge to its place on the page it's folded into. The words are
 * lang/en/site.php courses.pages.breath-and-stamina.
 */
final class BreathAndStaminaPage
{
    /** The held note's steady seconds (the scoring line's own example), on a scale whose goals stop at 15. */
    public const HOLD = 9.4;

    private const SLUG = 'breath-and-stamina';

    private const SCALE = 15;

    /** How far along the scale the held note stops, in percent. */
    public static function holdAt(): float
    {
        return round(self::HOLD / self::SCALE * 100, 2);
    }

    /** A number to one decimal place, written as the page's language writes it. */
    public static function tenths(float $value): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return number_format($value, 1);
        }
        $format = new NumberFormatter(app()->getLocale(), NumberFormatter::DECIMAL);
        $format->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 1);
        $format->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

        return (string) $format->format($value);
    }

    /**
     * The seconds marked under the held note, 0 to 14, each in the page's words (`:n s`).
     *
     * @return list<string>
     */
    public static function seconds(string $words): array
    {
        return array_map(static fn (int $n): string => str_replace(':n', (string) $n, $words), range(0, self::SCALE - 1));
    }

    /**
     * A line with the breath mark in it (`:mark`, drawn as the page's comma): the words before it and after it, so the
     * view prints both escaped around its own mark.
     *
     * @return array{0: string, 1: string}
     */
    public static function aroundMark(string $words): array
    {
        $parts = explode(':mark', $words, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Each unit's title, its line and its lessons: the lesson's name, its page's search title (a challenge's own line
     * from `challenges`, by lesson id) and its address; a course without lesson pages lists the names alone.
     *
     * @param  array<string, mixed>  $page  the course's words
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int, minutes?: int|null}|null  $outline
     * @return list<array{title: string, say: string, lessons: list<array{name: string, line: string, url: string|null}>}>
     */
    public static function units(array $page, ?array $outline): array
    {
        $byUnit = [];
        foreach (Lessons::pages(self::SLUG) as $lessonPage) {
            $byUnit[(int) $lessonPage['unitNumber']][] = $lessonPage;
        }
        $challenges = (array) ($page['challenges'] ?? []);
        $says = array_values((array) ($page['units_say'] ?? []));
        $units = [];

        foreach (array_values($outline['units'] ?? []) as $u => $unit) {
            $lessons = [];
            foreach ($byUnit[$u + 1] ?? [] as $lessonPage) {
                $group = array_values((array) $lessonPage['lessons']);
                foreach ($group as $i => $lesson) {
                    $lessons[] = [
                        'name' => (string) $lesson['name'],
                        'line' => $i === 0 ? (string) $lessonPage['title'] : (string) ($challenges[$lesson['id']] ?? ''),
                        'url' => $lessonPage['url'].(count($group) > 1 ? '#lesson-'.($i + 1) : ''),
                    ];
                }
            }
            if ($lessons === []) {
                $lessons = array_map(static fn (string $name): array => ['name' => $name, 'line' => '', 'url' => null], $unit['lessons']);
            }
            $units[] = ['title' => $unit['title'], 'say' => (string) ($says[$u] ?? ''), 'lessons' => $lessons];
        }

        return $units;
    }

    /**
     * The map's count line: units, lessons and the catalogue's minutes (`map_meta`), or units and lessons alone when the
     * catalogue gives no minutes.
     *
     * @param  array<string, mixed>  $page
     * @param  array{units: list<array{title: string, lessons: list<string>}>, lessons: int, minutes?: int|null}|null  $outline
     */
    public static function mapMeta(array $page, ?array $outline): string
    {
        $units = count($outline['units'] ?? []);
        $lessons = (int) ($outline['lessons'] ?? 0);
        $minutes = $outline['minutes'] ?? null;

        return is_int($minutes)
            ? str_replace([':units', ':lessons', ':minutes'], [(string) $units, (string) $lessons, (string) $minutes], (string) $page['map_meta'])
            : (string) __('pages.site.course_size', ['units' => $units, 'lessons' => $lessons]);
    }
}
