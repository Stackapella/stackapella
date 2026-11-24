<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SingingTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A counted singing test, every item heard about 20 cents off.
 *
 * @extends Factory<SingingTest>
 */
final class SingingTestFactory extends Factory
{
    protected $model = SingingTest::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'test_version' => 1,
            'device_scoring_version' => 1,
            'taken_at' => now(),
            'anchor' => '57.0',
            'device' => 'phone',
            'warm_up' => false,
            'items' => self::items(20.0),
            'device_score' => null,
            'scored_version' => 0,
            'status' => 'counted',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(static fn (SingingTest $test): SingingTest => $test->rescore());
    }

    /**
     * Twelve match notes, six patterns and two holds, each off by $cents (alternately flat and sharp).
     *
     * @return array{match: list<array<string, mixed>>, back: list<array<string, mixed>>, hold: list<array<string, mixed>>}
     */
    public static function items(float $cents): array
    {
        return [
            'match' => array_map(static fn (int $i): array => ['target' => 57 + $i % 5, 'centsOff' => $i % 2 ? $cents : -$cents, 'octave' => 0, 'landingMs' => 320, 'jitterCents' => 6.0, 'startAfterPromptMs' => 400], range(0, 11)),
            'back' => array_fill(0, 6, ['pattern' => [57, 59, 61, 59], 'notes' => array_map(static fn (int $i): array => ['centsOff' => $i % 2 ? $cents : -$cents, 'jitterCents' => 6.0], range(0, 3)), 'startOffsetCents' => 20.0, 'startAfterPromptMs' => 380]),
            'hold' => [['target' => 57, 'spreadCents' => 10.0, 'driftCents' => -8.0], ['target' => 59, 'spreadCents' => 12.0, 'driftCents' => 6.0]],
        ];
    }
}
