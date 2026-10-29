<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutSession>
 */
final class CheckoutSessionFactory extends Factory
{
    protected $model = CheckoutSession::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'stripe_id' => 'cs_test_'.Str::random(24), 'moment' => 'settings', 'interval' => 'year', 'currency' => 'usd', 'trial' => true];
    }
}
