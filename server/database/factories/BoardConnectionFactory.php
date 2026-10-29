<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BoardConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardConnection>
 */
final class BoardConnectionFactory extends Factory
{
    protected $model = BoardConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'friend_id' => User::factory(),
            'source' => BoardConnection::CHALLENGE,
            'created_at' => now(),
        ];
    }
}
