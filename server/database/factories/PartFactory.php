<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Part;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Part>
 */
final class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'project_id' => Project::factory(),
            'order' => 0,
            'name' => 'Alto',
            'color' => '#E4A94B',
            'volume' => '1.000',
            'pan' => '0.000',
            'muted' => false,
            'solo' => false,
            'nudge_frames' => 0,
            'trim_start_frames' => 0,
            'trim_end_frames' => 0,
            'template_part_key' => null,
            'current_take_id' => null,
            'settings' => [],
            'version' => 1,
            'seq' => 1,
        ];
    }
}
