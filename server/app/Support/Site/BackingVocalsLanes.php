<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * The Backing vocals course page's lanes (site/courses/backing-vocals, Hillel's design of 2026-11-18): the backing parts
 * drawn as stacked coloured lanes, like a multitrack, from real notes.
 *
 * - hero(): Shenandoah's first two lines (beats 0 to 16). The lead is the tune from its song template; the pad is the
 *   course's own (Shenandoah's pad, its first play step), placed in the template's key by the tune's first note, which the
 *   lesson's tune shares; the doo-wop and the answer are drawn on the template's chords, to show where each kind sits.
 * - grace(): Amazing Grace's first line (beats 0 to 12, in 3/4), its four parts from the template the beginner course
 *   sings.
 *
 * Each note is [at, len, midi, word, left, width]: its beat, its length in beats, its pitch, the word sung on it (trimmed
 * of hyphens and stops), and its place in its lane in percent. A file that's missing gives empty lanes, never an error.
 */
final class BackingVocalsLanes
{
    public const HERO_BEATS = 16;

    public const GRACE_BEATS = 12;

    /** Where the doo-wop's "doo" and the answer sit: at or above these MIDI notes. */
    private const DOO_LOW = 57;

    private const ANSWER_LOW = 64;

    /**
     * @return array{tempo: int, span: int, lanes: array{lead: list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>, pad: list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>, doo: list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>, answer: list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>}}
     */
    public static function hero(): array
    {
        $song = self::json(base_path('database/templates/shenandoah.json'));
        $tune = self::partNotes($song, 'soprano', self::HERO_BEATS);
        $chords = array_values(array_filter((array) ($song['chords'] ?? []), 'is_array'));

        // The pad: the course's lesson, its first play step's notes, in scale steps from do.
        $lesson = self::lesson('backing-vocals', 'bv-1-shenandoah');
        $pad = [];
        $heard = [];
        foreach ((array) ($lesson['steps'] ?? []) as $step) {
            if (! is_array($step)) {
                continue;
            }
            if ($pad === [] && ($step['kind'] ?? '') === 'play' && is_array($step['exercise']['notes'] ?? null)) {
                $pad = array_values(array_filter($step['exercise']['notes'], 'is_array'));
            }
            if ($heard === [] && is_array($step['listen']['notes'] ?? null)) {
                $heard = array_values(array_filter($step['listen']['notes'], 'is_array'));
            }
        }
        $doh = $tune !== [] && $heard !== [] ? $tune[0]['midi'] - (int) ($heard[0]['step'] ?? 0) : 65;

        $lanes = ['lead' => $tune, 'pad' => [], 'doo' => [], 'answer' => []];
        foreach ($pad as $note) {
            $lanes['pad'][] = self::note((float) ($note['beat'] ?? 0), (float) ($note['length'] ?? 0), $doh + (int) ($note['step'] ?? 0), (string) ($note['lyric'] ?? 'oo'), self::HERO_BEATS);
        }

        // A doo-wah every two beats from the downbeat, the figure of unit 2's Doo-wah ("doo" on the chord's third, "wah"
        // on its fifth), shortened to Shenandoah's slower beat; on the chord the figure mostly sits on (a passing half-beat
        // chord, the B flat over F at beat 5, doesn't count).
        foreach ([1, 3, 5, 7, 9, 11, 13] as $beat) {
            $root = self::rootAt($chords, $beat + 0.5);
            $lanes['doo'][] = self::note($beat, 0.75, self::place($root + 4, self::DOO_LOW), 'doo', self::HERO_BEATS);
            $lanes['doo'][] = self::note($beat + 0.75, 1.0, self::place($root + 7, self::DOO_LOW), 'wah', self::HERO_BEATS);
        }

        // An answer in each line's last long note: "a-way", the chord's third falling to its root, under the tune.
        foreach ([[0, 8], [8, self::HERO_BEATS]] as [$from, $to]) {
            $last = null;
            foreach ($tune as $note) {
                if ($note['at'] >= $from && $note['at'] < $to) {
                    $last = $note;
                }
            }
            if ($last === null) {
                continue;
            }
            $at = $last['at'] + 0.5;
            $root = self::rootAt($chords, $at);
            $lanes['answer'][] = self::note($at, 0.5, self::place($root + 4, self::ANSWER_LOW), 'a-', self::HERO_BEATS);
            $lanes['answer'][] = self::note($at + 0.5, $to - $at - 0.5, self::place($root, self::ANSWER_LOW), 'way', self::HERO_BEATS);
        }

        return ['tempo' => (int) ($song['tempo'] ?? 63), 'span' => self::HERO_BEATS, 'lanes' => $lanes];
    }

    /**
     * Amazing Grace's four parts, soprano to bass, its first line.
     *
     * @return array{span: int, parts: list<list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>>}
     */
    public static function grace(): array
    {
        $song = self::json(rtrim((string) config('app.web_path'), '/').'/templates/amazing-grace.json');

        return [
            'span' => self::GRACE_BEATS,
            'parts' => array_map(static fn (string $key): array => self::partNotes($song, $key, self::GRACE_BEATS), ['soprano', 'alto', 'tenor', 'bass']),
        ];
    }

    /**
     * A part's notes that start before `$span` beats.
     *
     * @param  array<array-key, mixed>  $song
     * @return list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>
     */
    private static function partNotes(array $song, string $key, int $span): array
    {
        $notes = [];
        foreach ((array) ($song['parts'] ?? []) as $part) {
            if (! is_array($part) || ($part['key'] ?? null) !== $key) {
                continue;
            }
            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                if (is_array($note) && (float) ($note['beat'] ?? 0) < $span) {
                    $notes[] = self::note((float) $note['beat'], (float) ($note['length'] ?? 0), (int) ($note['pitch'] ?? 60), (string) ($note['lyric'] ?? ''), $span);
                }
            }
        }

        return $notes;
    }

    /** @return array{at: float, len: float, midi: int, word: string, left: float, width: float} */
    private static function note(float $at, float $len, int $midi, string $word, int $span): array
    {
        return ['at' => $at, 'len' => $len, 'midi' => $midi, 'word' => rtrim($word, '-,.;!'), 'left' => round($at / $span * 100, 3), 'width' => round($len / $span * 100, 3)];
    }

    /**
     * The chord's root (a pitch class) sounding at `$beat`.
     *
     * @param  list<array<array-key, mixed>>  $chords
     */
    private static function rootAt(array $chords, float $beat): int
    {
        $root = 0;
        foreach ($chords as $chord) {
            if ((float) ($chord['beat'] ?? 0) <= $beat) {
                $root = (int) ($chord['root'] ?? 0);
            }
        }

        return $root;
    }

    /** A pitch class (any integer, taken mod 12) placed at or above `$low`, a MIDI note. */
    private static function place(int $pitchClass, int $low): int
    {
        return $low + ((($pitchClass - $low) % 12) + 12) % 12;
    }

    /**
     * A lesson of a course in web/course/plus/<course>/course.json, or [] when there's none.
     *
     * @return array<array-key, mixed>
     */
    private static function lesson(string $course, string $id): array
    {
        $file = self::json(rtrim((string) config('app.web_path'), '/').'/course/plus/'.$course.'/course.json');
        foreach ((array) ($file['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $lesson) {
                if (is_array($lesson) && ($lesson['id'] ?? null) === $id) {
                    return $lesson;
                }
            }
        }

        return [];
    }

    /** @return array<array-key, mixed> */
    private static function json(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
