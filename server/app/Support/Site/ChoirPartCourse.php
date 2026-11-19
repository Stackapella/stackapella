<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * What the Learn your part for rehearsal page (site/courses/learn-your-choir-part) reads from the course itself
 * (web/course/plus/choir-part/course.json and the catalogue), in the page's language, so it follows the course when the
 * course changes (its song swaps on 2026-12-28):
 *
 * - firstLesson(): lesson 1 as the app runs it: its opening card and three steps, the part on its own (the course's
 *   default part's card and each other part's own), the find-the-notes step, and the sung lines with what they're
 *   scored on.
 * - findings(): the course's four sourced facts (each lesson's "Did you know?"), oldest first, each with the unit whose
 *   lesson tells it, and the Sources list they number into.
 * - minutes(): the catalogue's length for the course.
 *
 * A missing file or field gives nothing, never an error.
 */
final class ChoirPartCourse
{
    private const ID = 'choir-part';

    /**
     * @return array{intro: string, cards: list<array{title: string, detail: string}>, parts: array<string, array{headline: string, cards: list<array{title: string, detail: string}>, n: int}>, find: string, sing: string, scored: string}|null
     */
    public static function firstLesson(): ?array
    {
        $course = self::course();
        $lesson = ((array) (((array) ($course['units'][0] ?? []))['lessons'] ?? []))[0] ?? null;
        if (! is_array($lesson)) {
            return null;
        }
        $default = is_string($course['parts']['default'] ?? null) ? $course['parts']['default'] : 'alto';
        $out = ['intro' => '', 'cards' => [], 'parts' => [], 'find' => '', 'sing' => '', 'scored' => ''];
        $learns = 0;
        foreach ((array) ($lesson['steps'] ?? []) as $step) {
            if (! is_array($step)) {
                continue;
            }
            $kind = $step['kind'] ?? '';
            if ($kind === 'learn' && $learns === 0) {
                $out['intro'] = self::words($step['headline'] ?? '');
                $out['cards'] = self::cards($step['steps'] ?? []);
                $learns++;
            } elseif ($kind === 'learn') {
                $out['parts'][$default] = ['headline' => self::words($step['headline'] ?? ''), 'cards' => self::cards($step['steps'] ?? [])];
                foreach ((array) ($step['byPart'] ?? []) as $key => $own) {
                    if (is_string($key) && is_array($own)) {
                        $out['parts'][$key] = ['headline' => self::words($own['headline'] ?? ''), 'cards' => self::cards($own['steps'] ?? [])];
                    }
                }
                $learns++;
            } elseif ($kind === 'try' && $out['find'] === '') {
                $out['find'] = self::words($step['text'] ?? '');
            } elseif ($kind === 'play' && $out['sing'] === '') {
                $out['sing'] = self::words($step['text'] ?? '');
                $out['scored'] = self::words($step['measures'] ?? '');
            }
        }
        // Soprano to bass, the order the page picks them in.
        $parts = [];
        foreach (ChoirPartScore::PARTS as $i => $key) {
            if (isset($out['parts'][$key])) {
                $parts[$key] = [...$out['parts'][$key], 'n' => $i + 1];
            }
        }
        $out['parts'] = $parts;

        return $out['intro'] === '' ? null : $out;
    }

    /**
     * The course's facts, oldest first: [id, year, text, source, url, unit].
     *
     * @return list<array{id: string, year: int, text: string, source: string, url: string, unit: int}>
     */
    public static function findings(): array
    {
        $facts = [];
        foreach ((array) (self::course()['units'] ?? []) as $u => $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                $fact = is_array($lesson) ? ($lesson['fact'] ?? null) : null;
                if (! is_array($fact) || ! is_string($fact['id'] ?? null) || ! is_string($fact['url'] ?? null)) {
                    continue;
                }
                $source = self::words($fact['source'] ?? '');
                $year = preg_match('/\((\d{4})\)/', $source, $m) === 1 ? (int) $m[1] : 0;
                $facts[] = ['id' => $fact['id'], 'year' => $year, 'text' => self::words($fact['text'] ?? ''), 'source' => $source, 'url' => $fact['url'], 'unit' => (int) $u + 1];
            }
        }
        usort($facts, static fn (array $a, array $b): int => $a['year'] <=> $b['year']);

        return $facts;
    }

    /**
     * The page's Sources list, numbered as findings() runs: fact id => [source, url].
     *
     * @param  list<array{id: string, year: int, text: string, source: string, url: string, unit: int}>  $findings
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sources(array $findings): array
    {
        $sources = [];
        foreach ($findings as $finding) {
            $sources[$finding['id']] = [$finding['source'], $finding['url']];
        }

        return $sources;
    }

    /** The course's length in minutes, from the catalogue, or null. */
    public static function minutes(): ?int
    {
        $list = self::json(self::root().'/courses.json');
        foreach ((array) ($list['courses'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['id'] ?? null) === self::ID && is_int($entry['minutes'] ?? null)) {
                return $entry['minutes'];
            }
        }

        return null;
    }

    /**
     * @return list<array{title: string, detail: string}>
     */
    private static function cards(mixed $steps): array
    {
        $cards = [];
        foreach ((array) $steps as $card) {
            if (is_array($card)) {
                $cards[] = ['title' => self::words($card['title'] ?? ''), 'detail' => self::words($card['detail'] ?? '')];
            }
        }

        return $cards;
    }

    /** A course text in the page's language, else in English. */
    private static function words(mixed $text): string
    {
        if (is_string($text)) {
            return $text;
        }
        if (! is_array($text)) {
            return '';
        }
        $said = $text[app()->getLocale()] ?? $text['en'] ?? '';

        return is_string($said) ? $said : '';
    }

    /** @return array<array-key, mixed> */
    private static function course(): array
    {
        return self::json(self::root().'/'.self::ID.'/course.json');
    }

    private static function root(): string
    {
        return rtrim((string) config('app.web_path'), '/').'/course/plus';
    }

    /** @return array<array-key, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
