<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

/**
 * Who sings which part (docs/choir-together-plan.md): a part's fit for a voice is the share of its sung time inside the
 * voice's range. A friend joining is given the parts their voice fits better than the owner's (a tie stays with the
 * owner), among the parts no other friend has; a voice may sing several. Pure, so its fixtures say exactly what it does.
 */
final class ChoirAssign
{
    /**
     * @param  list<array<string, mixed>>  $notes  a part's guide notes (beat, length, pitch)
     * @param  array{0: int, 1: int}  $range  MIDI low and high, inclusive
     */
    public static function fit(array $notes, array $range): float
    {
        $sung = 0.0;
        $inside = 0.0;

        foreach ($notes as $note) {
            if (! isset($note['pitch'])) {
                continue;
            }

            $length = (float) ($note['length'] ?? 0);
            $sung += $length;

            if ((int) $note['pitch'] >= $range[0] && (int) $note['pitch'] <= $range[1]) {
                $inside += $length;
            }
        }

        return $sung > 0 ? $inside / $sung : 0.0;
    }

    /**
     * The parts a new friend sings.
     *
     * @param  array<string, list<array<string, mixed>>>  $parts  the parts the friend may sing (the invite's named ones),
     *                                                            each part's notes, by key, in the song's order
     * @param  array{0: int, 1: int}  $owner
     * @param  array{0: int, 1: int}  $friend
     * @param  list<string>  $taken  parts another friend already sings
     * @param  int|null  $songParts  the song's parts in all (the owner keeps one); by default those given
     * @return list<string>
     */
    public static function forFriend(array $parts, array $owner, array $friend, array $taken = [], ?int $songParts = null): array
    {
        $won = [];

        foreach ($parts as $key => $notes) {
            if (! in_array($key, $taken, true) && self::fit($notes, $friend) > self::fit($notes, $owner)) {
                $won[] = (string) $key;
            }
        }

        // Never every part, with the other friends': the owner keeps at least one (it's their song).
        $room = max(0, ($songParts ?? count($parts)) - 1 - count(array_unique($taken)));

        return array_slice($won, 0, $room);
    }

    /**
     * Whether a part sits mostly above or below a voice (under half its sung time inside the range), or fits (null).
     *
     * @param  list<array<string, mixed>>  $notes
     * @param  array{0: int, 1: int}  $range
     * @return 'high'|'low'|null
     */
    public static function lean(array $notes, array $range): ?string
    {
        if (self::fit($notes, $range) >= 0.5) {
            return null;
        }

        $pitches = array_values(array_filter(array_map(static fn (array $n): ?int => isset($n['pitch']) ? (int) $n['pitch'] : null, $notes), static fn (?int $p): bool => $p !== null));
        sort($pitches);
        $median = $pitches === [] ? 0 : $pitches[intdiv(count($pitches), 2)];

        return $median > $range[1] ? 'high' : ($median < $range[0] ? 'low' : null);
    }

    /**
     * A part's range widened two semitones each way: a voice's range from the part they sang along to.
     *
     * @param  list<array<string, mixed>>  $notes
     * @return array{0: int, 1: int}|null
     */
    public static function fromPart(array $notes): ?array
    {
        $pitches = array_values(array_filter(array_map(static fn (array $n): ?int => isset($n['pitch']) ? (int) $n['pitch'] : null, $notes), static fn (?int $p): bool => $p !== null));

        return $pitches === [] ? null : [min($pitches) - 2, max($pitches) + 2];
    }
}
