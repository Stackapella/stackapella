<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DailyScore;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyScore>
 */
final class DailyScoreFactory extends Factory
{
    protected $model = DailyScore::class;

    public function definition(): array
    {
        return [
            'date' => now()->utc()->toDateString(),
            'user_id' => User::factory(),
            'template_id' => Template::factory(),
            'template_version_id' => fn (array $row): int => TemplateVersion::factory()->create(['template_id' => $row['template_id']])->id,
            'part_key' => 'alto',
            'scoring_version' => 1,
            'score' => fake()->numberBetween(40, 95),
            'recorded_at' => now(),
        ];
    }
}
