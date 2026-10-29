<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StoreSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoreSubscription>
 */
final class StoreSubscriptionFactory extends Factory
{
    protected $model = StoreSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store' => StoreSubscription::APPLE,
            'original_id' => (string) random_int(100000000000, 999999999999),
            'product_id' => 'com.stackapella.plus.year',
            'interval' => 'year',
            'status' => StoreSubscription::ACTIVE,
            'expires_at' => now()->addYear(),
            'will_renew' => true,
            'environment' => 'Production',
        ];
    }

    public function google(): static
    {
        $token = Str::random(40);

        return $this->state(fn (): array => ['store' => StoreSubscription::GOOGLE, 'original_id' => hash('sha256', $token), 'purchase_token' => $token, 'token_hash' => hash('sha256', $token), 'product_id' => 'plus']);
    }
}
