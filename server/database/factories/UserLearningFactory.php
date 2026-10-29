<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLearning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLearning>
 */
final class UserLearningFactory extends Factory
{
    protected $model = UserLearning::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'progress' => ['xp' => 0], 'version' => 1];
    }
}
