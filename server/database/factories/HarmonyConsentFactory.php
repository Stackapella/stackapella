<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Harmony;
use App\Models\HarmonyConsent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarmonyConsent>
 */
final class HarmonyConsentFactory extends Factory
{
    protected $model = HarmonyConsent::class;

    public function definition(): array
    {
        return [
            'harmony_id' => Harmony::factory(),
            'user_id' => User::factory(),
            'status' => HarmonyConsent::ASKED,
        ];
    }
}
