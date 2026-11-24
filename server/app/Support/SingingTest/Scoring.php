<?php

declare(strict_types=1);

namespace App\Support\SingingTest;

/**
 * The singing test's score from its item numbers (docs/singing-test-v1.md), as the app's engine computes it: the rule
 * and the cases both test against are contract/singing-test/scoring-v1.json. Each event's points lie between a fixed
 * ceiling (full marks) and floor (none) in cents, so one singer's result never moves another's; a new scoring version
 * is a new set of these numbers, and every kept test is scored again with it.
 */
final class Scoring
{
    /** The scoring every kept test is scored with now. */
    public const VERSION = 1;

    /** Each event's weight, ceiling and floor (cents), version 1: provisional until calibration (the plan's step 2). */
    private const EVENTS = [
        'match' => ['weight' => 45, 'ceiling' => 10.0, 'floor' => 150.0],
        'back' => ['weight' => 40, 'ceiling' => 15.0, 'floor' => 200.0],
        'hold' => ['weight' => 15, 'ceiling' => 8.0, 'floor' => 80.0],
    ];

    /** Fewer heard than these is practice: kept, never counted. */
    private const HEARD = ['match' => 8, 'patterns' => 4, 'holds' => 1];

    /** Each band's lowest mean cents off (the edge goes to the worse band); under the last, precise. */
    private const BANDS = ['finding' => 100.0, 'closer' => 60.0, 'nearly' => 35.0, 'inTune' => 15.0];

    /**
     * @param  list<array<string, mixed>>  $match
     * @param  list<array<string, mixed>>  $back
     * @param  list<array<string, mixed>>  $hold
     * @return array{status: string, scores: array{match: float|null, back: float|null, hold: float|null}, total: int|null, band: string|null}
     */
    public static function score(array $match, array $back, array $hold, bool $warmUp): array
    {
        $cents = static fn (mixed $value): ?float => is_int($value) || is_float($value) ? (float) $value : null;

        $matched = array_values(array_filter(array_map(static fn (array $item): ?float => $cents($item['centsOff'] ?? null), $match), static fn (?float $c): bool => $c !== null));
        $notes = [];
        $patterns = 0;

        foreach ($back as $pattern) {
            $heard = array_values(array_filter(array_map(static fn (mixed $note): ?float => is_array($note) ? $cents($note['centsOff'] ?? null) : null, (array) ($pattern['notes'] ?? [])), static fn (?float $c): bool => $c !== null));
            $notes = [...$notes, ...$heard];
            $patterns += count($heard) >= 3 ? 1 : 0;
        }

        $holds = [];

        foreach ($hold as $item) {
            $spread = $cents($item['spreadCents'] ?? null);
            $drift = $cents($item['driftCents'] ?? null);

            if ($spread !== null && $drift !== null) {
                $holds[] = $spread + abs($drift) / 2;
            }
        }

        $points = [
            'match' => self::points('match', self::absolute($matched)),
            'back' => self::points('back', self::absolute($notes)),
            'hold' => self::points('hold', $holds),
        ];

        $practice = count($matched) < self::HEARD['match'] || $patterns < self::HEARD['patterns'] || count($holds) < self::HEARD['holds'];
        $pitch = self::absolute([...$matched, ...$notes]);

        return [
            'status' => $practice ? 'practice' : ($warmUp ? 'warmup' : 'counted'),
            'scores' => array_map(static fn (?float $p): ?float => $p === null ? null : round($p, 1), $points),
            // Rounded once, from the events' unrounded points (half up: PHP's round, as every value is positive).
            'total' => $practice ? null : (int) round(array_sum(array_map(static fn (?float $p): float => $p ?? 0.0, $points))),
            'band' => $practice || $pitch === [] ? null : self::band(array_sum($pitch) / count($pitch)),
        ];
    }

    /**
     * An event's points: its weight times where its mean error lies between ceiling and floor; null when nothing in it
     * was heard.
     *
     * @param  list<float>  $errors
     */
    private static function points(string $event, array $errors): ?float
    {
        if ($errors === []) {
            return null;
        }

        ['weight' => $weight, 'ceiling' => $ceiling, 'floor' => $floor] = self::EVENTS[$event];
        $error = array_sum($errors) / count($errors);

        return $weight * max(0.0, min(1.0, ($floor - $error) / ($floor - $ceiling)));
    }

    /**
     * @param  list<float>  $cents
     * @return list<float>
     */
    private static function absolute(array $cents): array
    {
        return array_values(array_map(static fn (float $c): float => abs($c), $cents));
    }

    private static function band(float $cents): string
    {
        foreach (self::BANDS as $band => $from) {
            if ($cents >= $from) {
                return $band;
            }
        }

        return 'precise';
    }
}
