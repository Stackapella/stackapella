<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MonthlyScore;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyScore>
 */
final class MonthlyScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => MonthlyScore::thisMonth(),
            'user_id' => User::factory(),
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'scoring_version' => 1,
            'score' => fake()->numberBetween(40, 95),
            'recorded_at' => now(),
        ];
    }
}
