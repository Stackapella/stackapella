<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

/**
 * The low-voice version of a song (Hillel, 2026-11-23: a low voice's soprano and alto came out high and thin): the treble
 * parts an octave down, tenor and bass as written. Octaves only, so every chord tone is kept; a moved note that would
 * fall below the bass sounding with it stays at pitch, so the bass stays the lowest note. Pure: data in, data out.
 */
final class VoicingTransform
{
    /**
     * @param  array<string, mixed>  $data  a template.json
     * @return array{data: array<string, mixed>, shifts: array<string, int>}
     */
    public static function low(array $data): array
    {
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), static fn (mixed $p): bool => is_array($p) && is_string($p['key'] ?? null)));
        $notes = [];

        foreach ($parts as $part) {
            $notes[$part['key']] = array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array'));
        }

        $bass = self::bassKey($notes);
        $shifts = [];

        foreach ($notes as $key => $list) {
            $shifts[$key] = $key !== $bass && self::treble($key, $list) ? -12 : 0;
        }

        $data['parts'] = array_map(static function (mixed $part) use ($shifts, $notes, $bass): mixed {
            if (! is_array($part) || ($shifts[$part['key'] ?? ''] ?? 0) === 0) {
                return $part;
            }

            $part['guide']['notes'] = array_map(static function (mixed $n) use ($notes, $bass): mixed {
                if (! is_array($n) || ! is_numeric($n['pitch'] ?? null)) {
                    return $n;
                }

                $moved = (int) $n['pitch'] - 12;
                $under = $bass === null ? null : self::lowestDuring($notes[$bass], (float) ($n['beat'] ?? 0), (float) ($n['length'] ?? 0));

                return $under !== null && $moved < $under ? $n : array_replace($n, ['pitch' => $moved]);
            }, (array) $part['guide']['notes']);

            return $part;
        }, (array) ($data['parts'] ?? []));

        return ['data' => $data, 'shifts' => $shifts];
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
