<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDevice>
 */
final class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'device_hash' => hash('sha256', fake()->uuid()), 'last_seen_at' => now()];
    }
}
