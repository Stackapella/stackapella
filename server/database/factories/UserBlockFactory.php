<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBlock>
 */
final class UserBlockFactory extends Factory
{
    protected $model = UserBlock::class;

    public function definition(): array
    {
        return [
            'blocker_id' => User::factory(),
            'blocked_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
