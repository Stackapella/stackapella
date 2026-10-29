<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReviewItem;
use App\Models\ReviewMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReviewMedia>
 */
final class ReviewMediaFactory extends Factory
{
    protected $model = ReviewMedia::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'review_item_id' => ReviewItem::factory(),
            'user_id' => User::factory(),
            'kind' => ReviewMedia::VOICE_NOTE,
            'status' => ReviewMedia::READY,
            'sample_rate' => 48000,
            'frames' => 48000 * 5,
            'upload_bytes' => 48000 * 10 + 44,
            'sha256' => hash('sha256', fake()->uuid()),
            'upload_key' => 'uploads/reviews/'.Str::lower(Str::random(32)).'.wav',
            'file_key' => 'reviews/'.Str::lower(Str::random(32)).'.m4a',
        ];
    }
}
