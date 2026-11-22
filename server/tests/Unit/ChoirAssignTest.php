<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OwnChoir\ChoirAssign;
use PHPUnit\Framework\TestCase;

/**
 * Who sings which part (docs/choir-together-plan.md): fixtures for the pure rule.
 */
final class ChoirAssignTest extends TestCase
{
    /** @return array<string, list<array<string, mixed>>> four parts, each held on its own two notes */
    private static function song(): array
    {
        $part = static fn (int $low, int $high): array => [['beat' => 0, 'length' => 2, 'pitch' => $low], ['beat' => 2, 'length' => 2, 'pitch' => $high]];

        return ['soprano' => $part(67, 74), 'alto' => $part(60, 67), 'tenor' => $part(52, 59), 'bass' => $part(43, 50)];
    }

    public function test_a_high_friend_takes_the_high_parts_from_a_low_owner(): void
    {
        $this->assertSame(['soprano', 'alto'], ChoirAssign::forFriend(self::song(), [40, 59], [58, 77]));
    }

    public function test_a_low_friend_takes_the_low_parts_from_a_high_owner(): void
    {
        $this->assertSame(['tenor', 'bass'], ChoirAssign::forFriend(self::song(), [58, 77], [40, 59]));
    }

    public function test_a_tie_stays_with_the_owner(): void
    {
        $this->assertSame([], ChoirAssign::forFriend(self::song(), [40, 80], [40, 80]));
    }

    public function test_four_friends_each_take_what_no_one_has_and_the_owner_keeps_one(): void
    {
        $song = self::song();
        $owner = [30, 35];
        $taken = [];

        foreach ([[66, 75], [59, 68], [51, 60], [42, 51]] as $friend) {
            $won = ChoirAssign::forFriend($song, $owner, $friend, $taken);
            $taken = [...$taken, ...$won];
        }

        // The fourth would take the last part: the owner keeps at least one.
        $this->assertSame(['soprano', 'alto', 'tenor'], $taken);
    }

    public function test_a_range_from_the_part_sung_along_to_is_widened_two_semitones(): void
    {
        $this->assertSame([41, 52], ChoirAssign::fromPart(self::song()['bass']));
        $this->assertSame('high', ChoirAssign::lean(self::song()['soprano'], [41, 52]));
        $this->assertSame('low', ChoirAssign::lean(self::song()['bass'], [60, 77]));
        $this->assertNull(ChoirAssign::lean(self::song()['bass'], [41, 52]));
    }
}
