<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Score>
 */
final class ScoreFactory extends Factory
{
    protected $model = Score::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'user_id' => User::factory(), 'take_uuid' => null, 'template_slug' => 'amazing-grace', 'template_version' => 1, 'part_key' => 'alto', 'lesson' => null, 'recorded_at' => now(), 'summary' => ['pitchPercent' => 88]];
    }
}
