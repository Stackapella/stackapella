<?php

declare(strict_types=1);

namespace App\Support\Templates;

/**
 * The arranger's checks on an official template (docs/arrangements-final-review.md, "How it was
 * checked"), kept so no later edit brings a problem back. Two kinds:
 * - **hard:** broken data, which `ArrangementCheckTest` refuses: notes that overlap or have no
 *   length, parts that don't line up (or run past the song), a pitch outside a voice's hard range,
 *   a phrase or a part without its words;
 * - **advice:** what the review judges by ear, listed for the musician: a pitch outside the amateur
 *   range, a leap over a sixth (an alto or tenor one even into a new phrase), parallel fifths and
 *   octaves (passing notes allowed), a long note on the beat that isn't in its chord, a 2- or
 *   3-singer voicing without the chord's third (and a last chord without it), a duet's bare fourth,
 *   fifth, octave or second on a strong beat, and the bar arithmetic
 *   (docs/arrangements-voicings-plan.md).
 * A round (its parts sing the same tune, entering at different beats) is exempt from the
 * parallels, the inner voices' leaps and every voicing check: its clashes are its own.
 */
final class ArrangementCheck
{
    /** The comfortable ranges for amateur voices (the review's ceilings and floors), MIDI. */
    public const AMATEUR = ['soprano' => [60, 76], 'alto' => [55, 72], 'tenor' => [48, 67], 'bass' => [41, 62]];

    /** Beyond these is broken data, not a hard note to sing. */
    public const HARD = ['soprano' => [55, 81], 'alto' => [52, 77], 'tenor' => [45, 70], 'bass' => [36, 65]];

    /**
     * Barbershop's (TemplateRules::isBarbershop): the lead sings the tune where a tenor would, the
     * tenor sits above it, the baritone between the lead and the bass (the arranger's numbers; the
     * tenor's are provisional until Down by the Old Mill Stream is drafted).
     */
    public const AMATEUR_BARBERSHOP = ['tenor' => [55, 72], 'lead' => [48, 67], 'baritone' => [45, 62], 'bass' => [41, 62]];

    public const HARD_BARBERSHOP = ['tenor' => [52, 75], 'lead' => [45, 70], 'baritone' => [42, 65], 'bass' => [36, 65]];

    /** Each chord quality's tones above its root, and its third (null: none to keep). */
    public const QUALITIES = [
        'maj' => [[0, 4, 7], 4], 'min' => [[0, 3, 7], 3], 'dom7' => [[0, 4, 7, 10], 4], 'maj6' => [[0, 4, 7, 9], 4],
        'min7' => [[0, 3, 7, 10], 3], 'sus2' => [[0, 2, 7], null], 'sus4' => [[0, 5, 7], null],
        'dim' => [[0, 3, 6], 3], 'aug' => [[0, 4, 8], 4], 'maj7' => [[0, 4, 7, 11], 4], 'dim7' => [[0, 3, 6, 9], 3],
        'hdim7' => [[0, 3, 6, 10], 3], 'min6' => [[0, 3, 7, 9], 3], 'add9' => [[0, 2, 4, 7], 4],
    ];

    /** Leaps larger than a major sixth (semitones). */
    private const LEAP = 9;

    private const EPSILON = 1e-6;

    private const NAMES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

    /**
     * @param  array<string, mixed>  $t  the template, decoded as arrays
     * @return array{hard: list<string>, advice: list<string>}
     */
    public function check(array $t): array
    {
        $hard = [];
        $advice = [];
        $parts = $this->parts($t);
        $end = (float) max([0, ...array_map(static fn (array $s): float => (float) ($s['endBeat'] ?? 0), (array) ($t['sections'] ?? []))]);
        $end = $end > 0 ? $end : TemplateRules::lengthBeats((array) ($t['parts'] ?? []));
        $round = $this->isRound($parts);

        foreach ($parts as $key => $notes) {
            $this->line($t, $key, $notes, $end, $round, $hard, $advice);
        }

        if (! $round) {
            $this->parallels($t, $parts, $advice);
        }

        $this->chords($t, $parts, $round, $advice);

        if (! $round) {
            $this->duet($t, $parts, $advice);
        }

        $this->bars($t, $end, $advice);

        return ['hard' => $hard, 'advice' => $advice];
    }

    /**
     * A round: its parts enter at different beats and sing the same tune (the same first notes, in
     * any octave). A solo pickup or a voice that comes in later isn't one.
     *
     * @param  array<string, list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>>  $parts
     */
    private function isRound(array $parts): bool
    {
        $starts = array_unique(array_map(static fn (array $notes): string => (string) ($notes[0]['beat'] ?? 0), $parts));
        $length = min([6, ...array_map('count', $parts)]);
        $tunes = array_unique(array_map(static fn (array $notes): string => implode(',', array_map(static fn (array $n): int => $n['pitch'] % 12, array_slice($notes, 0, $length))), $parts));

        return count($parts) > 1 && count($starts) > 1 && count($tunes) === 1;
    }

    /**
     * Each part's notes, sorted by beat.
     *
     * @param  array<string, mixed>  $t
     * @return array<string, list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>>
     */
    private function parts(array $t): array
    {
        $parts = [];

        foreach ((array) ($t['parts'] ?? []) as $part) {
            $notes = array_map(static fn (array $n): array => [
                'beat' => (float) ($n['beat'] ?? 0), 'length' => (float) ($n['length'] ?? 0), 'pitch' => (int) ($n['pitch'] ?? 0),
                'lyric' => isset($n['lyric']) && is_string($n['lyric']) && trim($n['lyric']) !== '' ? $n['lyric'] : null, 'br' => ($n['br'] ?? false) === true,
            ], array_values(array_filter((array) ($part['guide']['notes'] ?? []), 'is_array')));
            usort($notes, static fn (array $a, array $b): int => $a['beat'] <=> $b['beat']);
            $parts[(string) ($part['key'] ?? '?')] = $notes;
        }

        return $parts;
    }

    /**
     * One part on its own: its timing, range, leaps and words.
     *
     * @param  array<string, mixed>  $t
     * @param  list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>  $notes
     * @param  list<string>  $hard
     * @param  list<string>  $advice
     */
    private function line(array $t, string $key, array $notes, float $end, bool $round, array &$hard, array &$advice): void
    {
        if ($notes === []) {
            $hard[] = "{$key}: no notes";

            return;
        }

        $last = end($notes);
        $partEnd = $last['beat'] + $last['length'];

        if ($partEnd > $end + self::EPSILON) {
            $hard[] = "{$key}: runs to beat {$partEnd}, past the song's end ({$end})";
        } elseif (! $round && abs($partEnd - $end) > self::EPSILON) {
            $hard[] = "{$key}: ends at beat {$partEnd}, not with the song ({$end}): the parts don't line up";
        }

        if (array_filter($notes, static fn (array $n): bool => $n['lyric'] !== null) === []) {
            $hard[] = "{$key}: no words at all";
        } elseif ($notes[0]['lyric'] === null) {
            $hard[] = "{$key}: its first note, {$this->at($t, $notes[0]['beat'])}, has no syllable";
        }

        $barbershop = TemplateRules::isBarbershop($t);
        [$low, $high] = ($barbershop ? self::HARD_BARBERSHOP : self::HARD)[$key] ?? [0, 127];
        [$comfyLow, $comfyHigh] = ($barbershop ? self::AMATEUR_BARBERSHOP : self::AMATEUR)[$key] ?? [0, 127];

        foreach ($notes as $i => $note) {
            $at = "{$key} {$this->at($t, $note['beat'])}";

            if ($note['length'] <= self::EPSILON) {
                $hard[] = "{$at}: a note with no length";
            }

            $next = $notes[$i + 1] ?? null;

            if ($next !== null && $next['beat'] < $note['beat'] + $note['length'] - self::EPSILON) {
                $hard[] = "{$at}: overlaps the next note ({$this->at($t, $next['beat'])})";
            }

            if ($note['br'] && $note['lyric'] === null) {
                $hard[] = "{$at}: a phrase starts with no syllable";
            }

            if ($note['pitch'] < $low || $note['pitch'] > $high) {
                $hard[] = "{$at}: {$this->name($note['pitch'])} is outside the {$key}'s hard range ({$this->name($low)} to {$this->name($high)})";
            } elseif ($note['pitch'] < $comfyLow || $note['pitch'] > $comfyHigh) {
                $advice[] = "range: {$at}: {$this->name($note['pitch'])} is outside the amateur {$key} range ({$this->name($comfyLow)} to {$this->name($comfyHigh)})";
            }

            // A leap within a phrase (a new phrase may start anywhere).
            if ($next !== null && ! $next['br'] && abs($next['pitch'] - $note['pitch']) > self::LEAP) {
                $advice[] = "leap: {$at}: {$this->name($note['pitch'])} to {$this->name($next['pitch'])}, over a sixth";
            } elseif ($next !== null && $next['br'] && ! $round && in_array($key, ['alto', 'tenor'], true) && abs($next['pitch'] - $note['pitch']) > self::LEAP) {
                // An inner voice has no tune to find its next note by, so a breath doesn't help it.
                $advice[] = "leap: {$at}: {$this->name($note['pitch'])} to {$this->name($next['pitch'])} into a new phrase, over a sixth";
            }
        }
    }

    /**
     * Parallel fifths and octaves between every pair of voices: both move the same way from one
     * perfect interval to the same one. Moves to or from a short note (under a beat) are passing
     * notes, and allowed.
     *
     * @param  array<string, mixed>  $t
     * @param  array<string, list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>>  $parts
     * @param  list<string>  $advice
     */
    private function parallels(array $t, array $parts, array &$advice): void
    {
        $keys = array_keys($parts);

        foreach ($keys as $i => $a) {
            foreach (array_slice($keys, $i + 1) as $b) {
                $onsets = array_values(array_unique(array_map(static fn (array $n): string => (string) $n['beat'], [...$parts[$a], ...$parts[$b]])));
                usort($onsets, static fn (string $x, string $y): int => (float) $x <=> (float) $y);
                $previous = null;

                foreach ($onsets as $onset) {
                    $beat = (float) $onset;
                    $x = $this->sounding($parts[$a], $beat);
                    $y = $this->sounding($parts[$b], $beat);

                    if ($x === null || $y === null) {
                        $previous = null;

                        continue;
                    }

                    if ($previous !== null) {
                        [$px, $py] = $previous;
                        $interval = abs($x['pitch'] - $y['pitch']) % 12;
                        $before = abs($px['pitch'] - $py['pitch']) % 12;
                        $moveX = $x['pitch'] <=> $px['pitch'];
                        $moveY = $y['pitch'] <=> $py['pitch'];
                        $short = min($x['length'], $y['length'], $px['length'], $py['length']) < 1 - self::EPSILON;

                        if (in_array($interval, [0, 7], true) && $interval === $before && $moveX !== 0 && $moveX === $moveY && ! $short) {
                            $kind = $interval === 7 ? 'fifths' : 'octaves';
                            $advice[] = "parallels: {$a} and {$b}, {$this->at($t, $beat)}: parallel {$kind} ({$this->name($px['pitch'])}/{$this->name($py['pitch'])} to {$this->name($x['pitch'])}/{$this->name($y['pitch'])})";
                        }
                    }

                    $previous = [$x, $y];
                }
            }
        }
    }

    /**
     * Notes against the `chords` field, and each smaller voicing's third. Only long notes on a
     * whole beat are judged against the chord: shorter or off-beat ones are passing notes.
     *
     * @param  array<string, mixed>  $t
     * @param  array<string, list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>>  $parts
     * @param  list<string>  $advice
     */
    private function chords(array $t, array $parts, bool $round, array &$advice): void
    {
        $chords = array_values(array_filter((array) ($t['chords'] ?? []), 'is_array'));

        if ($chords === []) {
            return;
        }

        usort($chords, static fn (array $a, array $b): int => (float) $a['beat'] <=> (float) $b['beat']);

        foreach ($parts as $key => $notes) {
            foreach ($notes as $note) {
                if ($note['length'] < 1 - self::EPSILON || abs($note['beat'] - round($note['beat'])) > self::EPSILON) {
                    continue;
                }

                $chord = $this->chordAt($chords, $note['beat']);
                $tones = $chord !== null ? $this->tones($chord) : null;

                if ($tones !== null && ! in_array($note['pitch'] % 12, $tones, true)) {
                    $advice[] = "chord: {$key} {$this->at($t, $note['beat'])}: {$this->name($note['pitch'])} isn't in {$this->chordName($chord)}";
                }
            }
        }

        foreach ($round ? [] : ['2', '3'] as $size) {
            $voices = array_values(array_filter((array) ($t['voicings'][$size] ?? []), static fn (mixed $k): bool => is_string($k) && isset($parts[$k])));

            if ($voices === []) {
                continue;
            }

            // The last chord starts where the voicing's last note does; it's judged there too, since
            // its chord may have begun earlier (unless that beat was judged already).
            $last = max(array_map(static fn (string $k): float => $parts[$k] !== [] ? $parts[$k][count($parts[$k]) - 1]['beat'] : 0.0, $voices));
            $judged = false;

            foreach ($chords as $chord) {
                $third = self::QUALITIES[$chord['quality'] ?? ''][1] ?? null;
                $judged = $judged || abs((float) $chord['beat'] - $last) < self::EPSILON;

                if ($third === null || ! $this->missingThird($parts, $voices, $chord, (float) $chord['beat'])) {
                    continue;
                }

                $advice[] = "third: the {$size}-singer voicing (".implode(', ', $voices)."), {$this->at($t, (float) $chord['beat'])}: {$this->chordName($chord)} without its third";
            }

            $chord = $this->chordAt($chords, $last);

            if (! $judged && $chord !== null && $this->missingThird($parts, $voices, $chord, $last)) {
                $advice[] = "end: the {$size}-singer voicing (".implode(', ', $voices)."), {$this->at($t, $last)}: the last chord, {$this->chordName($chord)}, without its third";
            }
        }
    }

    /**
     * Whether the voices sing a chord without its third at a beat (a chord with no third to keep,
     * a sus, never misses it).
     *
     * @param  array<string, list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>>  $parts
     * @param  list<string>  $voices
     * @param  array<string, mixed>  $chord
     */
    private function missingThird(array $parts, array $voices, array $chord, float $beat): bool
    {
        $third = self::QUALITIES[$chord['quality'] ?? ''][1] ?? null;
        $sung = array_filter(array_map(fn (string $k): ?int => $this->sounding($parts[$k], $beat)['pitch'] ?? null, $voices), 'is_int');

        return $third !== null && count($sung) > 1 && ! in_array(((int) $chord['root'] + $third) % 12, array_map(static fn (int $p): int => $p % 12, $sung), true);
    }

    /**
     * The duet on its own: a bare fourth, fifth or octave, or a second, between its two voices on a
     * strong beat (the bar's first, or the third in 4/4) held for a beat or more sounds empty or
     * clashes with nothing under it. A fourth or fifth is fine when the tune holds the chord's
     * sixth or seventh (the alto on the root of a C6 or Cm7).
     *
     * @param  array<string, mixed>  $t
     * @param  array<string, list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>>  $parts
     * @param  list<string>  $advice
     */
    private function duet(array $t, array $parts, array &$advice): void
    {
        $voices = array_values(array_filter((array) ($t['voicings']['2'] ?? []), static fn (mixed $k): bool => is_string($k) && isset($parts[$k])));

        if (count($voices) !== 2) {
            return;
        }

        $chords = array_values(array_filter((array) ($t['chords'] ?? []), 'is_array'));
        usort($chords, static fn (array $a, array $b): int => (float) $a['beat'] <=> (float) $b['beat']);
        $perBar = TemplateRules::beatsPerBar((string) ($t['timeSignature'] ?? '4/4'));
        $onsets = array_values(array_unique(array_map(static fn (array $n): string => (string) $n['beat'], [...$parts[$voices[0]], ...$parts[$voices[1]]])));
        usort($onsets, static fn (string $x, string $y): int => (float) $x <=> (float) $y);
        $kinds = [0 => 'an octave', 1 => 'a second', 2 => 'a second', 5 => 'a bare fourth', 7 => 'a bare fifth'];

        foreach ($onsets as $i => $onset) {
            $beat = (float) $onset;
            $within = fmod(fmod($beat - (float) ($t['pickupBeats'] ?? 0), $perBar) + $perBar, $perBar);
            $strong = abs($within) < self::EPSILON || abs($within - $perBar) < self::EPSILON || ($perBar === 4 && abs($within - 2) < self::EPSILON);
            $held = (isset($onsets[$i + 1]) ? (float) $onsets[$i + 1] : INF) - $beat;
            $x = $this->sounding($parts[$voices[0]], $beat);
            $y = $this->sounding($parts[$voices[1]], $beat);

            if (! $strong || $held < 1 - self::EPSILON || $x === null || $y === null) {
                continue;
            }

            [$high, $low] = $x['pitch'] >= $y['pitch'] ? [$x['pitch'], $y['pitch']] : [$y['pitch'], $x['pitch']];
            $interval = ($high - $low) % 12;
            $chord = $chords !== [] ? $this->chordAt($chords, $beat) : null;
            $tones = $chord !== null ? (self::QUALITIES[$chord['quality'] ?? ''][0] ?? []) : [];
            $colour = isset($tones[3]) && ($chord['quality'] ?? '') !== 'add9' && $high % 12 === ((int) $chord['root'] + $tones[3]) % 12;

            if (isset($kinds[$interval]) && ! ($colour && in_array($interval, [5, 7], true))) {
                $advice[] = 'duet: the 2-singer voicing ('.implode(', ', $voices)."), {$this->at($t, $beat)}: {$kinds[$interval]} ({$this->name($high)} over {$this->name($low)})";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $t
     * @param  list<string>  $advice
     */
    private function bars(array $t, float $end, array &$advice): void
    {
        $perBar = TemplateRules::beatsPerBar((string) ($t['timeSignature'] ?? '4/4'));
        $over = fmod($end, $perBar);

        if ($over > self::EPSILON && abs($over - $perBar) > self::EPSILON) {
            $advice[] = "bars: the song ends {$over} beat(s) into a bar (".($t['timeSignature'] ?? '4/4').', pickup '.($t['pickupBeats'] ?? 0).'): a held last note, or a beat short?';
        }
    }

    /**
     * @param  list<array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}>  $notes
     * @return array{beat: float, length: float, pitch: int, lyric: ?string, br: bool}|null
     */
    private function sounding(array $notes, float $beat): ?array
    {
        foreach ($notes as $note) {
            if ($note['beat'] <= $beat + self::EPSILON && $beat < $note['beat'] + $note['length'] - self::EPSILON) {
                return $note;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $chords
     * @return array<string, mixed>|null
     */
    private function chordAt(array $chords, float $beat): ?array
    {
        $found = null;

        foreach ($chords as $chord) {
            if ((float) $chord['beat'] <= $beat + self::EPSILON) {
                $found = $chord;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $chord
     * @return list<int>|null null for a quality the check doesn't know
     */
    private function tones(array $chord): ?array
    {
        $quality = self::QUALITIES[$chord['quality'] ?? ''] ?? null;

        if ($quality === null) {
            return null;
        }

        $tones = array_map(static fn (int $i): int => ((int) $chord['root'] + $i) % 12, $quality[0]);

        return isset($chord['bass']) && is_int($chord['bass']) ? [...$tones, $chord['bass'] % 12] : $tones;
    }

    /**
     * @param  array<string, mixed>  $chord
     */
    private function chordName(array $chord): string
    {
        $name = self::NAMES[(int) $chord['root'] % 12].' '.($chord['quality'] ?? '?');

        return isset($chord['bass']) && is_int($chord['bass']) ? $name.'/'.self::NAMES[$chord['bass'] % 12] : $name;
    }

    /**
     * Where a beat is, as the review writes it: bar.beat (the template's beat), bar 0 the pickup.
     *
     * @param  array<string, mixed>  $t
     */
    private function at(array $t, float $beat): string
    {
        $perBar = TemplateRules::beatsPerBar((string) ($t['timeSignature'] ?? '4/4'));
        $from = $beat - (float) ($t['pickupBeats'] ?? 0);
        $bar = (int) floor($from / $perBar) + 1;
        $within = round(fmod(fmod($from, $perBar) + $perBar, $perBar) + 1, 3);

        return $bar.'.'.$within.' ('.round($beat, 3).')';
    }

    private function name(int $pitch): string
    {
        return self::NAMES[$pitch % 12].(intdiv($pitch, 12) - 1);
    }
}
