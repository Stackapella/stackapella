<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PartScore;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartScore>
 */
final class PartScoreFactory extends Factory
{
    protected $model = PartScore::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_id' => Template::factory(),
            'template_version_id' => fn (array $row): int => TemplateVersion::factory()->create(['template_id' => $row['template_id']])->id,
            'part_key' => 'alto',
            'scoring_version' => 1,
            'score' => fake()->numberBetween(40, 95),
            'mode' => 'own',
            'key_offset' => 0,
            'speed' => '1.00',
            'guide_muted' => true,
            'timing_checked' => true,
            'output' => 'wired',
            'pitch_detector_version' => 1,
            'recorded_at' => now(),
        ];
    }
}
