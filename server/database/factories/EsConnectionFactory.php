<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EsConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EsConnection>
 */
final class EsConnectionFactory extends Factory
{
    protected $model = EsConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mode' => EsConnection::OAUTH,
            'base_url' => 'https://eventschedule.com',
            'es_user_id' => 'U'.Str::random(6),
            'es_user_name' => 'Grace Okafor',
            'access_token' => 'at-'.Str::random(20),
            'refresh_token' => 'rt-'.Str::random(20),
            'expires_at' => now()->addHour(),
            'scopes' => ['profile', 'schedules:read', 'appointments:read', 'appointments:write', 'events:read', 'webhooks:manage', 'members:read'],
            'status' => EsConnection::ACTIVE,
        ];
    }

    public function withSchedule(string $schedule = 'grace-sings'): self
    {
        return $this->state(['schedule' => $schedule, 'schedule_name' => 'Grace Sings', 'schedule_timezone' => 'Europe/London', 'webhook_id' => 'wh-'.Str::random(6), 'webhook_secret' => 'whsec-'.Str::random(24)]);
    }
}
