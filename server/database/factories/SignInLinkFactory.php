<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SignInLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SignInLink>
 */
final class SignInLinkFactory extends Factory
{
    protected $model = SignInLink::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(40)),
            'context' => ['intended' => '/', 'invite' => null, 'early' => null],
            'expires_at' => now()->addMinutes(SignInLink::MINUTES),
        ];
    }
}
