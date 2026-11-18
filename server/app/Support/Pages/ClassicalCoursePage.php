<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Str;

/**
 * Classical singing basics' own course page (site/courses/classical-singing-basics, Hillel's design, 2026-11-18): what
 * its view shows that it reads from elsewhere, so the view stays markup. Lesson 1's sung phrase and the line the app
 * shows under its result, both from the course file itself; the songs as a programme; each unit's line.
 */
final class ClassicalCoursePage
{
    /** The movements' numerals, in order. */
    public const NUMERALS = ['I', 'II', 'III', 'IV', 'V', 'VI'];

    /**
     * Step 0 of the course's lines, as MIDI, for the hero's score: A flat 4 puts lesson 1's phrase (Caro mio ben's
     * first) in Parisotti's key, E flat, as the song's template has it, from E flat 5 down to G 4.
     */
    public const HOME = 68;

    /** The key and pickup the phrase is engraved in (the song's template: E flat, two beats before the first bar). */
    public const KEY = 'Eb';

    public const PICKUP = 2;

    /**
     * Lesson 1's last sung step: its notes as [beat, beats, MIDI], and its `measures` line in the page's language
     * (else English). Empty notes when the course file or the step isn't there, and the page shows no score.
     *
     * @return array{notes: list<array{0: float|int, 1: float|int, 2: int}>, measures: string}
     */
    public static function firstPhrase(?string $path = null): array
    {
        $path ??= dirname(base_path()).'/web/course/plus/classical-singing/course.json';
        $course = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $steps = is_array($course) ? (array) ($course['units'][0]['lessons'][0]['steps'] ?? []) : [];

        $sung = null;
        foreach ($steps as $step) {
            if (is_array($step) && ($step['kind'] ?? null) === 'play' && is_array($step['exercise']['notes'] ?? null)) {
                $sung = $step;
            }
        }

        if ($sung === null) {
            return ['notes' => [], 'measures' => ''];
        }

        $notes = [];
        foreach ((array) $sung['exercise']['notes'] as $note) {
            if (is_array($note) && is_numeric($note['beat'] ?? null) && is_numeric($note['length'] ?? null) && is_numeric($note['step'] ?? null)) {
                $notes[] = [$note['beat'] + 0, $note['length'] + 0, self::HOME + (int) $note['step']];
            }
        }
        $measures = (array) ($sung['measures'] ?? []);

        return ['notes' => $notes, 'measures' => (string) ($measures[app()->getLocale()] ?? $measures['en'] ?? '')];
    }

    /**
     * The songs (`[title, credit]` pairs) as a programme: a title's bracketed end set apart as its aside, and each
     * credit's last part (after its last ";") its status, said once (`status`) when every song shares it.
     *
     * @param  array<array-key, mixed>  $songs
     * @return array{pieces: list<array{title: string, aside: string|null, credit: string, status: string|null}>, status: string|null}
     */
    public static function programme(array $songs): array
    {
        $pieces = [];
        foreach ($songs as $song) {
            if (! is_array($song) || ! is_string($song[0] ?? null) || ! is_string($song[1] ?? null)) {
                continue;
            }
            [$title, $credit] = [$song[0], $song[1]];
            $aside = preg_match('/^(.+?)\s*(\([^)]*\))$/u', $title, $m) === 1 ? [$m[1], $m[2]] : [$title, null];
            $cut = strrpos($credit, ';');
            $pieces[] = [
                'title' => $aside[0],
                'aside' => $aside[1],
                'credit' => $cut === false ? $credit : trim(substr($credit, 0, $cut)),
                'status' => $cut === false ? null : trim(substr($credit, $cut + 1)),
            ];
        }

        $statuses = array_values(array_unique(array_map(static fn (array $p): ?string => $p['status'], $pieces)));
        $status = count($statuses) === 1 && is_string($statuses[0]) ? Str::ucfirst($statuses[0]) : null;

        return ['pieces' => $pieces, 'status' => $status];
    }

    /** A unit's line from the page's `learn` list ("Joined: notes with no gap ..."), without its unit's name. */
    public static function unitLine(mixed $learn): string
    {
        return is_string($learn) && $learn !== '' ? Str::ucfirst(Str::after($learn, ': ')) : '';
    }

    /** A movement's numeral (1 is I). */
    public static function numeral(int $n): string
    {
        return self::NUMERALS[$n - 1] ?? (string) $n;
    }
}
