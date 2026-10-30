<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ghost;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ghost>
 */
final class GhostFactory extends Factory
{
    protected $model = Ghost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'scoring_version' => 1,
            'score' => 80,
            'rate' => 20,
            'start_seconds' => '0.00',
            'key_offset' => 0,
            'pitches' => pack('v*', 0, 6900, 6900, 7100),
        ];
    }
}
