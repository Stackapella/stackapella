<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MudeoContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MudeoContact>
 */
final class MudeoContactFactory extends Factory
{
    protected $model = MudeoContact::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'mudeo_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'mudeo_created_at' => now()->subYears(3),
            'mudeo_updated_at' => now()->subYear(),
        ];
    }
}
