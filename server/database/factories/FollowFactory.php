<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
final class FollowFactory extends Factory
{
    protected $model = Follow::class;

    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'followee_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
