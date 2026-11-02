<?php

declare(strict_types=1);

namespace App\Support\Templates;

/**
 * Each part's coarse shape for Home's song covers (web/js/song-shape.js does the same, and both are
 * tested against the same songs): its phrases' place in the song and how high they sit against the
 * part's middle, as `[start, end, lean]` integers. No notes and no words: start and end are 0 to
 * 1000 of the song's span, lean is -6 to 6 semitones.
 */
final class SongShape
{
    /** At most this many phrases a part: the closest neighbours are joined first. */
    public const MOST_PHRASES = 24;

    /**
     * @param  array<string, mixed>  $template  a template's JSON
     * @return array<string, list<array{0: int, 1: int, 2: int}>> part key => its phrases
     */
    public static function of(array $template): array
    {
        $parts = [];

        foreach ((array) ($template['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null)) {
                $parts[$part['key']] = self::notes($part);
            }
        }

        $all = array_merge(...array_values($parts ?: [[]]));
        $from = $all === [] ? 0.0 : min(array_map(static fn (array $n): float => $n['beat'], $all));
        $to = $all === [] ? 0.0 : max(array_map(static fn (array $n): float => $n['beat'] + $n['length'], $all));
        $shapes = [];

        foreach ($parts as $key => $notes) {
            $shapes[$key] = self::part($notes, $from, $to);
        }

        return $shapes;
    }

    /**
     * A part's sung notes (a numeric pitch, a length over 0), in time order.
     *
     * @param  array<array-key, mixed>  $part
     * @return list<array{beat: float, length: float, pitch: float, br: bool}>
     */
    private static function notes(array $part): array
    {
        $notes = [];

        foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
            if (is_array($note) && is_numeric($note['pitch'] ?? null) && is_numeric($note['length'] ?? null) && (float) $note['length'] > 0 && is_numeric($note['beat'] ?? null)) {
                $notes[] = ['beat' => (float) $note['beat'], 'length' => (float) $note['length'], 'pitch' => (float) $note['pitch'], 'br' => ($note['br'] ?? false) === true];
            }
        }

        // A stable sort by beat, as JavaScript's.
        $order = array_keys($notes);
        usort($order, static fn (int $a, int $b): int => [$notes[$a]['beat'], $a] <=> [$notes[$b]['beat'], $b]);

        return array_map(static fn (int $i): array => $notes[$i], $order);
    }

    /**
     * @param  list<array{beat: float, length: float, pitch: float, br: bool}>  $notes
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private static function part(array $notes, float $from, float $to): array
    {
        if ($notes === []) {
            return [];
        }

        /** @var list<array{start: float, end: float, weight: float, sum: float}> $phrases */
        $phrases = [];

        foreach ($notes as $note) {
            $last = count($phrases) - 1;

            if ($last < 0 || $note['br'] || $note['beat'] >= $phrases[$last]['end'] + 1) {
                $phrases[] = ['start' => $note['beat'], 'end' => $note['beat'] + $note['length'], 'weight' => $note['length'], 'sum' => $note['pitch'] * $note['length']];

                continue;
            }

            $phrase = $phrases[$last];
            $phrases[$last] = ['start' => $phrase['start'], 'end' => max($phrase['end'], $note['beat'] + $note['length']), 'weight' => $phrase['weight'] + $note['length'], 'sum' => $phrase['sum'] + $note['pitch'] * $note['length']];
        }

        // Too many: join the neighbouring pair with the smallest gap (the first on ties).
        while (count($phrases) > self::MOST_PHRASES) {
            $at = 0;

            for ($i = 1; $i < count($phrases) - 1; $i++) {
                if ($phrases[$i + 1]['start'] - $phrases[$i]['end'] < $phrases[$at + 1]['start'] - $phrases[$at]['end']) {
                    $at = $i;
                }
            }

            $phrases[$at] = ['start' => $phrases[$at]['start'], 'end' => $phrases[$at + 1]['end'], 'weight' => $phrases[$at]['weight'] + $phrases[$at + 1]['weight'], 'sum' => $phrases[$at]['sum'] + $phrases[$at + 1]['sum']];
            array_splice($phrases, $at + 1, 1);
        }

        $weight = array_sum(array_column($phrases, 'weight'));
        $middle = $weight > 0 ? array_sum(array_column($phrases, 'sum')) / $weight : 0.0;
        $span = $to - $from;
        $place = static fn (float $beat): int => $span > 0 ? max(0, min(1000, self::round(($beat - $from) / $span * 1000))) : 0;

        return array_map(static fn (array $p): array => [$place($p['start']), $place($p['end']), max(-6, min(6, self::round($p['sum'] / $p['weight'] - $middle)))], $phrases);
    }

    /**
     * As JavaScript's Math.round: halves go up, for negatives too (-2.5 is -2).
     */
    private static function round(float $x): int
    {
        return (int) floor($x + 0.5);
    }
}
