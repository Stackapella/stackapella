<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Take;
use App\Models\User;
use App\Support\Trust\TrustLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrustLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_levels_0_and_1_from_our_own_records(): void
    {
        $levels = app(TrustLevel::class);

        $this->assertSame(0, $levels->of(User::factory()->create(['google_id' => null, 'email_verified_at' => null])));
        $this->assertSame(1, $levels->of(User::factory()->create(['google_id' => null])));
        $this->assertSame(1, $levels->of(User::factory()->create(['email_verified_at' => null])), 'Google verifies.');
        $this->assertSame(0, $levels->of(User::factory()->create(['google_id' => null, 'email' => 'x@mailinator.com'])), 'A disposable address stays new without Google or Apple.');
    }

    public function test_level_2_after_three_days_and_two_recorded_parts_counted_when_not_loaded(): void
    {
        $levels = app(TrustLevel::class);
        $singer = User::factory()->create(['created_at' => now()->subDays(4)])->fresh() ?? throw new \RuntimeException('no user');
        $this->assertSame(1, $levels->of($singer));

        Take::factory()->count(2)->create(['user_id' => $singer->id]);
        $this->assertSame(2, $levels->of($singer->fresh() ?? $singer), 'Strict models: a user loaded without ready_takes_count is counted, not refused.');
    }

    public function test_an_override_wins_and_a_lowering_expires(): void
    {
        $levels = app(TrustLevel::class);

        $this->assertSame(3, $levels->of(User::factory()->create(['trust_level_override' => 3])));

        $lowered = User::factory()->create();
        $lowered->forceFill(['trust_level_override' => null, 'trust_lowered_until' => now()->addDays(30)])->save();
        $this->assertSame(1, $levels->of($lowered));
    }
}
