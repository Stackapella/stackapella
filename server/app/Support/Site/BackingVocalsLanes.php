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
 * - firstPad(): lesson 1's pad under Amazing Grace's first two lines (beats 0 to 24), with the tune over it and its chords
 *   named by their notes, from the course's own lesson.
 *
 * Each note is [at, len, midi, word, left, width]: its beat, its length in beats, its pitch, the word sung on it (trimmed
 * of hyphens and stops), and its place in its lane in percent. A file that's missing gives empty lanes, never an error.
 */
final class BackingVocalsLanes
{
    public const HERO_BEATS = 16;

    public const GRACE_BEATS = 12;

    /** Lesson 1's first two lines of Amazing Grace, in 3/4. */
    public const PAD_BEATS = 24;

    /** The major scale's steps from do, by their do re mi names (the course's own, "ti" for the seventh). */
    private const SOLFA = [0 => 'do', 2 => 're', 4 => 'mi', 5 => 'fa', 7 => 'so', 9 => 'la', 11 => 'ti'];

    /** A chord's notes in steps from its root, by the course's chord qualities. */
    private const TONES = ['maj' => [0, 4, 7], 'min' => [0, 3, 7], 'dom7' => [0, 4, 7, 10], 'maj7' => [0, 4, 7, 11], 'min7' => [0, 3, 7, 10], 'maj6' => [0, 4, 7, 9]];

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
     * Lesson 1's pad against its chords: Amazing Grace's first two lines (beats 0 to 24, in 3/4), from the lesson's play
     * step that has the tune over the pad. The tune (its words), the chords (a run of one chord merged), each named by its
     * notes in do re mi with the pad's note marked where the chord holds it, and the pad, each note named.
     *
     * @return array{span: int, lead: list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>, pad: list<array{at: float, len: float, midi: int, word: string, left: float, width: float}>, chords: list<array{left: float, width: float, tones: list<array{name: string, shared: bool}>}>}
     */
    public static function firstPad(): array
    {
        $span = self::PAD_BEATS;
        $exercise = [];
        foreach ((array) (self::lesson('backing-vocals', 'bv-1-pad')['steps'] ?? []) as $step) {
            if (is_array($step) && ($step['kind'] ?? '') === 'play' && is_array($step['exercise']['with'][0]['notes'] ?? null)) {
                $exercise = $step['exercise'];
                break;
            }
        }

        $lead = [];
        foreach ((array) ($exercise['with'][0]['notes'] ?? []) as $note) {
            if (is_array($note) && (float) ($note['beat'] ?? 0) < $span) {
                $lead[] = self::note((float) $note['beat'], (float) ($note['length'] ?? 0), 60 + (int) ($note['step'] ?? 0), (string) ($note['lyric'] ?? ''), $span);
            }
        }

        $pad = [];
        foreach ((array) ($exercise['notes'] ?? []) as $note) {
            if (is_array($note) && (float) ($note['beat'] ?? 0) < $span) {
                $at = (float) $note['beat'];
                $pad[] = self::note($at, min((float) ($note['length'] ?? 0), $span - $at), 60 + (int) ($note['step'] ?? 0), self::solfa((int) ($note['step'] ?? 0)), $span);
            }
        }

        // The chords, a run of the same one merged, each lasting to the next one's start (or the span's end).
        $runs = [];
        foreach ((array) ($exercise['chords'] ?? []) as $chord) {
            if (! is_array($chord) || (float) ($chord['beat'] ?? 0) >= $span) {
                continue;
            }
            $root = (int) ($chord['root'] ?? 0);
            $quality = (string) ($chord['quality'] ?? 'maj');
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['root'] === $root && $runs[$last]['quality'] === $quality) {
                continue;
            }
            $runs[] = ['at' => (float) ($chord['beat'] ?? 0), 'root' => $root, 'quality' => $quality];
        }

        $chords = [];
        foreach ($runs as $i => $run) {
            $end = isset($runs[$i + 1]) ? $runs[$i + 1]['at'] : (float) $span;
            // The pad's notes sounding in this chord, by name.
            $held = [];
            foreach ($pad as $note) {
                if ($note['at'] < $end && $run['at'] < $note['at'] + $note['len']) {
                    $held[] = $note['word'];
                }
            }
            $chords[] = [
                'left' => round($run['at'] / $span * 100, 3),
                'width' => round(($end - $run['at']) / $span * 100, 3),
                'tones' => array_map(static function (int $tone) use ($run, $held): array {
                    $name = self::solfa($run['root'] + $tone);

                    return ['name' => $name, 'shared' => in_array($name, $held, true)];
                }, self::TONES[$run['quality']] ?? self::TONES['maj']),
            ];
        }

        return ['span' => $span, 'lead' => $lead, 'pad' => $pad, 'chords' => $chords];
    }

    /** A scale step from do (any integer) by its do re mi name. */
    private static function solfa(int $step): string
    {
        return self::SOLFA[(($step % 12) + 12) % 12] ?? '';
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
