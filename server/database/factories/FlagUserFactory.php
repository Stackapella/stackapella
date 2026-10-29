<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FlagUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlagUser>
 */
final class FlagUserFactory extends Factory
{
    protected $model = FlagUser::class;

    public function definition(): array
    {
        return [
            'flag' => 'gallery',
            'user_id' => User::factory(),
        ];
    }
}
