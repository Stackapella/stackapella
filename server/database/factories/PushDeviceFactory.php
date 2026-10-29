<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushDevice>
 */
final class PushDeviceFactory extends Factory
{
    protected $model = PushDevice::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::random(24), 'endpoint_hash' => fn (array $d): string => hash('sha256', (string) $d['endpoint']), 'p256dh' => 'BPk'.Str::random(84), 'auth' => Str::random(22), 'token_hash' => hash('sha256', Str::random(40))];
    }
}
