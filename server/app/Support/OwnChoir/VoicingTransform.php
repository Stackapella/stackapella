<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

/**
 * The low-voice version of a song (Hillel, 2026-11-23: a low voice's soprano and alto came out high and thin): the treble
 * parts an octave down, tenor and bass as written. Octaves only, so every chord tone is kept. The tune moves whole, so it
 * keeps its shape; where it would sound under the bass, that bass note moves down an octave if its own range allows, else
 * the tune crosses under it there. An inner part's note that would fall below the bass stays at pitch. Pure: data in,
 * data out.
 */
final class VoicingTransform
{
    /**
     * @param  array<string, mixed>  $data  a template.json
     * @return array{data: array<string, mixed>, shifts: array<string, int>}
     */
    public static function low(array $data): array
    {
        [$notes, $bass, $treble] = self::analyse($data);
        $tune = self::tuneKey($data, $treble);
        $shifts = array_map(static fn (bool $high): int => $high ? -12 : 0, $treble);
        // The tune an octave down whole, so it keeps its shape (Ma'oz Tzur's second note leapt up a seventh when moved
        // note by note); the bass makes room under it where it can.
        $low = $tune === null ? null : self::wholeDown($notes[$tune]);
        $bassNotes = $bass === null ? [] : ($low === null ? $notes[$bass] : self::makeRoom($notes[$bass], $low));

        $data['parts'] = array_map(static function (mixed $part) use ($treble, $tune, $low, $bass, $bassNotes): mixed {
            if (! is_array($part) || ! is_string($part['key'] ?? null)) {
                return $part;
            }

            return match (true) {
                $part['key'] === $tune && $low !== null => self::withNotes($part, $low),
                $part['key'] === $bass => self::withNotes($part, $bassNotes),
                $treble[$part['key']] ?? false => self::lowered($part, $bassNotes),
                default => $part,
            };
        }, (array) ($data['parts'] ?? []));

        return ['data' => self::inRange($data), 'shifts' => $shifts];
    }

    /**
     * Both octaves (Hillel liked as written and low together, 2026-11-23): each treble part as written and, beside it,
     * "<key>-low", the same part an octave down (the tune whole, the others by the same rule); tenor and bass once.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, shifts: array<string, int>}
     */
    public static function full(array $data): array
    {
        [$notes, $bass, $treble] = self::analyse($data);
        $tune = self::tuneKey($data, $treble);
        $lowTune = $tune === null ? null : self::wholeDown($notes[$tune]);
        $bassNotes = $bass === null ? [] : ($lowTune === null ? $notes[$bass] : self::makeRoom($notes[$bass], $lowTune));
        $parts = [];
        $shifts = [];

        foreach ((array) ($data['parts'] ?? []) as $part) {
            if (! is_array($part) || ! is_string($part['key'] ?? null)) {
                $parts[] = $part;

                continue;
            }

            $parts[] = $part['key'] === $bass ? self::withNotes($part, $bassNotes) : $part;
            $shifts[$part['key']] = 0;

            if ($treble[$part['key']] ?? false) {
                $low = $part['key'] === $tune && $lowTune !== null ? self::withNotes($part, $lowTune) : self::lowered($part, $bassNotes);
                $low['key'] = $part['key'].'-low';
                $low['name'] = trim((string) ($part['name'] ?? ucfirst($part['key']))).' (low)';
                unset($low['melody']);
                $parts[] = $low;
                $shifts[$low['key']] = -12;
            }
        }

        $data['parts'] = $parts;

        return ['data' => self::inRange($data), 'shifts' => $shifts];
    }

    /**
     * The song moved by some semitones, every part (the key its lead was sung in).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function transpose(array $data, int $semitones): array
    {
        if ($semitones === 0) {
            return $data;
        }

        $data['parts'] = array_map(static function (mixed $part) use ($semitones): mixed {
            if (is_array($part) && is_array($part['guide']['notes'] ?? null)) {
                $part['guide']['notes'] = array_map(static fn (mixed $n): mixed => is_array($n) && is_numeric($n['pitch'] ?? null) ? array_replace($n, ['pitch' => (int) $n['pitch'] + $semitones]) : $n, $part['guide']['notes']);
            }

            return $part;
        }, (array) ($data['parts'] ?? []));

        return $data;
    }

    /**
     * Every part within what the renderer sings well and its gates can hear (MIDI 36 to 84; voices, 2026-11-23): a part
     * that would leave it moves a whole octave toward the middle, keeping its chord tones.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function inRange(array $data): array
    {
        $data['parts'] = array_map(static function (mixed $part): mixed {
            if (! is_array($part) || ! is_array($part['guide']['notes'] ?? null)) {
                return $part;
            }

            $pitches = array_map(static fn (array $n): int => (int) $n['pitch'], array_filter($part['guide']['notes'], static fn (mixed $n): bool => is_array($n) && is_numeric($n['pitch'] ?? null)));

            if ($pitches === []) {
                return $part;
            }

            $shift = match (true) {
                min($pitches) < 36 && max($pitches) + 12 <= 84 => 12,
                max($pitches) > 84 && min($pitches) - 12 >= 36 => -12,
                default => 0,
            };

            if ($shift !== 0) {
                $part['guide']['notes'] = array_map(static fn (mixed $n): mixed => is_array($n) && is_numeric($n['pitch'] ?? null) ? array_replace($n, ['pitch' => (int) $n['pitch'] + $shift]) : $n, $part['guide']['notes']);
            }

            return $part;
        }, (array) ($data['parts'] ?? []));

        return $data;
    }

    /**
     * The song's part keys as a voicing sings them: with "full", each treble part followed by its "-low" twin.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function keys(array $data, string $voicing): array
    {
        [, , $treble] = self::analyse($data);
        $keys = [];

        foreach ($treble as $key => $high) {
            $keys[] = (string) $key;

            if ($voicing === 'full' && $high) {
                $keys[] = $key.'-low';
            }
        }

        return $keys;
    }

    /**
     * Each part's notes, the bass's key, and which parts are treble.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, list<array<string, mixed>>>, 1: string|null, 2: array<string, bool>}
     */
    private static function analyse(array $data): array
    {
        $notes = [];

        foreach ((array) ($data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null)) {
                $notes[$part['key']] = array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'));
            }
        }

        $bass = self::bassKey($notes);
        $treble = [];

        foreach ($notes as $key => $list) {
            $treble[$key] = $key !== $bass && self::treble($key, $list);
        }

        return [$notes, $bass, $treble];
    }

    /**
     * An inner part an octave down, a note that would fall below the bass sounding with it kept at pitch.
     *
     * @param  array<string, mixed>  $part
     * @param  list<array<string, mixed>>  $bassNotes
     * @return array<string, mixed>
     */
    private static function lowered(array $part, array $bassNotes): array
    {
        $part['guide']['notes'] = array_map(static function (mixed $n) use ($bassNotes): mixed {
            if (! is_array($n) || ! is_numeric($n['pitch'] ?? null)) {
                return $n;
            }

            $moved = (int) $n['pitch'] - 12;
            $under = $bassNotes === [] ? null : self::lowestDuring($bassNotes, (float) ($n['beat'] ?? 0), (float) ($n['length'] ?? 0));

            return $under !== null && $moved < $under ? $n : array_replace($n, ['pitch' => $moved]);
        }, (array) ($part['guide']['notes'] ?? []));

        return $part;
    }

    /**
     * The tune: the part marked `melody`, else the first treble part.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, bool>  $treble
     */
    private static function tuneKey(array $data, array $treble): ?string
    {
        foreach ((array) ($data['parts'] ?? []) as $part) {
            if (is_array($part) && ($part['melody'] ?? false) === true && ($treble[$part['key'] ?? ''] ?? false)) {
                return (string) $part['key'];
            }
        }

        $first = array_key_first(array_filter($treble));

        return $first === null ? null : (string) $first;
    }

    /**
     * Every note an octave down.
     *
     * @param  list<array<string, mixed>>  $notes
     * @return list<array<string, mixed>>
     */
    private static function wholeDown(array $notes): array
    {
        return array_map(static fn (array $n): array => is_numeric($n['pitch'] ?? null) ? array_replace($n, ['pitch' => (int) $n['pitch'] - 12]) : $n, $notes);
    }

    /**
     * The bass under the lowered tune: a bass note the tune would sound below moves an octave down, if that stays within
     * the bass's own written range (and what the renderer sings well); else the tune crosses under it for that note.
     *
     * @param  list<array<string, mixed>>  $bass
     * @param  list<array<string, mixed>>  $tune
     * @return list<array<string, mixed>>
     */
    private static function makeRoom(array $bass, array $tune): array
    {
        $pitches = array_map(static fn (array $n): int => (int) $n['pitch'], array_filter($bass, static fn (array $n): bool => is_numeric($n['pitch'] ?? null)));
        $floor = $pitches === [] ? 36 : max(36, min($pitches));

        return array_map(static function (array $n) use ($tune, $floor): array {
            if (! is_numeric($n['pitch'] ?? null)) {
                return $n;
            }

            $over = self::lowestDuring($tune, (float) ($n['beat'] ?? 0), (float) ($n['length'] ?? 0));

            return $over !== null && $over < (int) $n['pitch'] && (int) $n['pitch'] - 12 >= $floor ? array_replace($n, ['pitch' => (int) $n['pitch'] - 12]) : $n;
        }, $bass);
    }

    /**
     * A part with new notes.
     *
     * @param  array<string, mixed>  $part
     * @param  list<array<string, mixed>>  $notes
     * @return array<string, mixed>
     */
    private static function withNotes(array $part, array $notes): array
    {
        $part['guide']['notes'] = $notes;

        return $part;
    }

    /**
     * Whether a voice is low: the song's tune an octave down fits it better than as written.
     *
     * @param  list<array<string, mixed>>  $melody
     * @param  array{0: int, 1: int}  $range
     */
    public static function isLow(array $melody, array $range): bool
    {
        $down = array_map(static fn (array $n): array => is_numeric($n['pitch'] ?? null) ? array_replace($n, ['pitch' => (int) $n['pitch'] - 12]) : $n, $melody);

        return ChoirAssign::fit($down, $range) > ChoirAssign::fit($melody, $range);
    }

    /**
     * A treble part: soprano or alto by name, else one whose median pitch is at or above middle C.
     *
     * @param  list<array<string, mixed>>  $notes
     */
    private static function treble(string $key, array $notes): bool
    {
        if (in_array($key, ['soprano', 'alto'], true)) {
            return true;
        }

        if (in_array($key, ['tenor', 'bass', 'baritone'], true)) {
            return false;
        }

        $pitches = array_map(static fn (array $n): int => (int) $n['pitch'], array_filter($notes, static fn (array $n): bool => is_numeric($n['pitch'] ?? null)));
        sort($pitches);

        return $pitches !== [] && $pitches[intdiv(count($pitches), 2)] >= 60;
    }

    /**
     * The bass: the part named so, else the one with the lowest median pitch.
     *
     * @param  array<string, list<array<string, mixed>>>  $notes
     */
    private static function bassKey(array $notes): ?string
    {
        if (isset($notes['bass'])) {
            return 'bass';
        }

        $best = null;
        $lowest = PHP_INT_MAX;

        foreach ($notes as $key => $list) {
            $pitches = array_map(static fn (array $n): int => (int) $n['pitch'], array_filter($list, static fn (array $n): bool => is_numeric($n['pitch'] ?? null)));
            sort($pitches);

            if ($pitches !== [] && $pitches[intdiv(count($pitches), 2)] < $lowest) {
                [$best, $lowest] = [(string) $key, $pitches[intdiv(count($pitches), 2)]];
            }
        }

        return count($notes) > 1 ? $best : null;
    }

    /**
     * The lowest pitch sounding in a part during [beat, beat + length).
     *
     * @param  list<array<string, mixed>>  $notes
     */
    private static function lowestDuring(array $notes, float $beat, float $length): ?int
    {
        $low = null;

        foreach ($notes as $n) {
            $start = (float) ($n['beat'] ?? 0);

            if (is_numeric($n['pitch'] ?? null) && $start < $beat + max($length, 0.001) && $start + (float) ($n['length'] ?? 0) > $beat) {
                $low = $low === null ? (int) $n['pitch'] : min($low, (int) $n['pitch']);
            }
        }

        return $low;
    }
}
