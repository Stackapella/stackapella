<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppDevice>
 */
final class AppDeviceFactory extends Factory
{
    protected $model = AppDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'An iPhone',
            'platform' => 'ios',
            'access_hash' => hash('sha256', 'sat_'.Str::random(40)),
            'access_expires_at' => now()->addHour(),
            'refresh_hash' => hash('sha256', 'srt_'.Str::random(48)),
            'refresh_expires_at' => now()->addDays(90),
            'method' => 'password',
            'signed_in_at' => now(),
        ];
    }
}
