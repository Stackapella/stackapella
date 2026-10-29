<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'action' => 'flag.read_only',
            'actor_id' => User::factory(),
            'before' => ['value' => 'off'],
            'after' => ['value' => 'on'],
            'reason' => 'A test',
            'created_at' => now(),
        ];
    }
}
