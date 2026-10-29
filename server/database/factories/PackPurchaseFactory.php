<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExercisePack;
use App\Models\PackPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackPurchase>
 */
final class PackPurchaseFactory extends Factory
{
    protected $model = PackPurchase::class;

    public function definition(): array
    {
        return [
            'pack_id' => ExercisePack::factory(),
            'user_id' => User::factory(),
        ];
    }
}
