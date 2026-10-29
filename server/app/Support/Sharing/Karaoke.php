<?php

declare(strict_types=1);

namespace App\Support\Sharing;

/**
 * The words of a shared harmony, timed for the video (docs/more-features-plan.md, feature 4): each
 * syllable of the sung part, at its beat and the project's tempo, from where beat 0 falls in the
 * mix. A line starts at a note marked `br`; notes without a lyric carry the syllable before them
 * (a melisma). "A-" "maz-" "ing" read as one word, "Amazing".
 */
final class Karaoke
{
    /**
     * Every line of the part, with each syllable's start and end in seconds of the mix.
     *
     * @param  array<string, mixed>  $template  a template version's data (template.json)
     * @return list<list<array{text: string, start: float, end: float, joins: bool}>> `joins`: no space before the next syllable
     */
    public static function lines(array $template, string $partKey, float $bpm, float $beatZeroSeconds): array
    {
        $part = null;
        foreach ((array) ($template['parts'] ?? []) as $candidate) {
            if (is_array($candidate) && ($candidate['key'] ?? null) === $partKey) {
                $part = $candidate;
            }
        }

        /** @var list<array{beat?: int|float, length?: int|float, lyric?: string, br?: bool}> $notes */
        $notes = is_array($part) ? array_values((array) ($part['guide']['notes'] ?? [])) : [];
        $secondsPerBeat = 60 / max(1, $bpm);
        $lines = [];
        $line = [];

        foreach ($notes as $note) {
            $start = $beatZeroSeconds + (float) ($note['beat'] ?? 0) * $secondsPerBeat;
            $end = $start + (float) ($note['length'] ?? 0) * $secondsPerBeat;
            $lyric = trim((string) ($note['lyric'] ?? ''));

            if ($lyric === '') {
                // A melisma: the syllable before it lasts to the end of this note.
                $last = array_pop($line);

                if ($last !== null) {
                    $line[] = ['text' => $last['text'], 'start' => $last['start'], 'end' => round($end, 3), 'joins' => $last['joins']];
                }

                continue;
            }

            if (($note['br'] ?? false) === true && $line !== []) {
                $lines[] = $line;
                $line = [];
            }

            $joins = str_ends_with($lyric, '-');
            $line[] = ['text' => $joins ? rtrim($lyric, '-') : $lyric, 'start' => round($start, 3), 'end' => round($end, 3), 'joins' => $joins];
        }

        if ($line !== []) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * The lines sung inside [from, to) seconds, with their times made relative to `from`.
     *
     * @param  list<list<array{text: string, start: float, end: float, joins: bool}>>  $lines
     * @return list<list<array{text: string, start: float, end: float, joins: bool}>>
     */
    public static function section(array $lines, float $from, float $to): array
    {
        $kept = [];

        foreach ($lines as $line) {
            $syllables = array_values(array_filter($line, static fn (array $s): bool => $s['start'] >= $from - 0.05 && $s['start'] < $to));

            if ($syllables !== []) {
                $kept[] = array_map(static fn (array $s): array => ['text' => $s['text'], 'start' => round(max(0, $s['start'] - $from), 3), 'end' => round(min($to, $s['end']) - $from, 3), 'joins' => $s['joins']], $syllables);
            }
        }

        return $kept;
    }

    /**
     * A line as text, the syllables joined into words.
     *
     * @param  list<array{text: string, start: float, end: float, joins: bool}>  $line
     */
    public static function text(array $line, ?int $through = null): string
    {
        $text = '';

        foreach ($line as $i => $syllable) {
            if ($through !== null && $i > $through) {
                break;
            }

            $text .= $syllable['text'].($syllable['joins'] ? '' : ' ');
        }

        return rtrim($text);
    }
}
