<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateGuide>
 */
final class TemplateGuideFactory extends Factory
{
    protected $model = TemplateGuide::class;

    public function definition(): array
    {
        return [
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'beta_submission_id' => null,
            'is_default' => true,
            'media_key' => 'guides/'.fake()->uuid().'.m4a',
            'sample_rate' => 48000,
            'frames' => 48000 * 40,
            'offset_frames' => 0,
            'gain' => '1.000',
            'published_at' => now(),
        ];
    }
}
