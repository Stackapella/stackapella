<?php

declare(strict_types=1);

namespace App\Support\SingingTest;

use App\Models\SingingTest;

/**
 * Why a singing test is held for a look before its season shows on the global board (docs/singing-test-v1.md, "The
 * boards"; the part boards' 48-hour hold and review): numbers only, never a recording.
 */
final class SingingTestHolds
{
    /** A total this far over the singer's best counted total before (the part boards' JUMP). */
    public const JUMP = 25;

    /** Notes with less jitter than this are flat as a synth's; a real voice through a good mic is rarely under 2. */
    public const FLAT_CENTS = 1.0;

    /** The share of a test's measured notes that must be flat to hold it, and the fewest notes that can tell. */
    public const FLAT_SHARE = 0.75;

    public const FLAT_FEWEST = 8;

    /**
     * @return list<string> jump, flat and duplicate, in that order
     */
    public function reasons(SingingTest $test): array
    {
        $reasons = [];
        $before = SingingTest::query()->where('user_id', $test->user_id)->where('test_version', $test->test_version)->where('status', SingingTest::COUNTED)
            ->when($test->exists, fn ($query) => $query->whereKeyNot($test->id))->max('total');

        if ($test->total !== null && $before !== null && $test->total - (int) $before >= self::JUMP) {
            $reasons[] = 'jump';
        }

        if ($this->flat($test)) {
            $reasons[] = 'flat';
        }

        if ($test->items_hash !== null && SingingTest::query()->where('items_hash', $test->items_hash)->when($test->exists, fn ($query) => $query->whereKeyNot($test->id))->exists()) {
            $reasons[] = 'duplicate';
        }

        return $reasons;
    }

    /** Three quarters or more of the notes measured long enough (jitterCents set) show almost none. */
    private function flat(SingingTest $test): bool
    {
        $jitters = [];

        foreach ($test->items['match'] ?? [] as $item) {
            $jitters[] = $item['jitterCents'] ?? null;
        }

        foreach ($test->items['back'] ?? [] as $pattern) {
            foreach ((array) ($pattern['notes'] ?? []) as $note) {
                $jitters[] = is_array($note) ? ($note['jitterCents'] ?? null) : null;
            }
        }

        $measured = array_values(array_filter($jitters, static fn (mixed $j): bool => is_int($j) || is_float($j)));

        if (count($measured) < self::FLAT_FEWEST) {
            return false;
        }

        $flat = count(array_filter($measured, static fn (int|float $j): bool => $j < self::FLAT_CENTS));

        return $flat / count($measured) >= self::FLAT_SHARE;
    }
}
