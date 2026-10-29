<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditMode;
use App\Models\BetaSinger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BetaSinger>
 */
final class BetaSingerFactory extends Factory
{
    protected $model = BetaSinger::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'note' => null,
            'token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => null,
            'hourly_rate_cents' => 3000,
            'currency' => 'USD',
        ];
    }

    public function consented(int $version = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'consent_version' => $version,
            'consented_at' => now(),
            'credit_mode' => CreditMode::Name,
        ]);
    }

    /**
     * A singer whose invite was accepted by an account (one is made unless given).
     */
    public function accepted(?User $user = null): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id ?? User::factory()->state(['name' => 'Sarah K.']), 'accepted_at' => now()]);
    }

    /**
     * A singer whose invite token is known, for tests that use the link.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['token_hash' => hash('sha256', $token)]);
    }
}
