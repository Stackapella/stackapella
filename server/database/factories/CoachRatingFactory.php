<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachRating;
use App\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachRating>
 */
final class CoachRatingFactory extends Factory
{
    protected $model = CoachRating::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'singer_id' => fn (array $rating): int => (int) Engagement::query()->whereKey($rating['engagement_id'])->value('singer_id'),
            'coach_id' => fn (array $rating): int => (int) Engagement::query()->whereKey($rating['engagement_id'])->value('coach_id'),
            'stars' => 5,
            'text' => 'Clear and kind.',
        ];
    }
}
