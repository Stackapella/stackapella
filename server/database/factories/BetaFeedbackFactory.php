<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BetaFeedback>
 */
final class BetaFeedbackFactory extends Factory
{
    protected $model = BetaFeedback::class;

    public function definition(): array
    {
        return [
            'beta_singer_id' => BetaSinger::factory(),
            'template_slug' => 'amazing-grace',
            'part_key' => 'alto',
            'beat' => 12,
            'text' => 'This note feels wrong in bar 12.',
            'read_at' => null,
        ];
    }
}
