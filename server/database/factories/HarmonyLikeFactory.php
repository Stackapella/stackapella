<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Harmony;
use App\Models\HarmonyLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarmonyLike>
 */
final class HarmonyLikeFactory extends Factory
{
    protected $model = HarmonyLike::class;

    public function definition(): array
    {
        return [
            'harmony_id' => Harmony::factory(),
            'user_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
