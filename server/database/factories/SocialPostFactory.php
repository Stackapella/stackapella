<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialPost;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
final class SocialPostFactory extends Factory
{
    protected $model = SocialPost::class;

    public function definition(): array
    {
        return [
            'postable_type' => 'template',
            'postable_id' => Template::factory(),
            'occasion' => SocialPost::NEW_SONG,
            'network' => 'bluesky',
            'status' => SocialPost::AWAITING,
        ];
    }
}
