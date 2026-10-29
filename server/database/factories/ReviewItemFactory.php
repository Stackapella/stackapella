<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\ReviewItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReviewItem>
 */
final class ReviewItemFactory extends Factory
{
    protected $model = ReviewItem::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'status' => ReviewItem::REQUESTED,
            'take_key' => 'reviews/'.Str::lower(Str::random(32)).'.flac',
            'sample_rate' => 48000,
            'frames' => 48000 * 30,
            'question' => 'My high notes feel strained.',
        ];
    }
}
