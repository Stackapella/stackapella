<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailToken>
 */
final class EmailTokenFactory extends Factory
{
    protected $model = EmailToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purpose' => EmailToken::VERIFY,
            'email' => fake()->safeEmail(),
            'token_hash' => hash('sha256', fake()->uuid()),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ];
    }
}
