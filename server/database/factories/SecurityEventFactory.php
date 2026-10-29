<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Support\Trust\IpHash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityEvent>
 */
final class SecurityEventFactory extends Factory
{
    protected $model = SecurityEvent::class;

    public function definition(): array
    {
        $ip = fake()->ipv4();

        return [
            'type' => SecurityEventType::RateLimited,
            'user_id' => null,
            'ip' => $ip,
            'ip_hash' => IpHash::of($ip),
            'context' => ['limiter' => 'api'],
            'created_at' => now(),
        ];
    }
}
