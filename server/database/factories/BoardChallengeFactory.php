<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BoardChallenge;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BoardChallenge>
 */
final class BoardChallengeFactory extends Factory
{
    protected $model = BoardChallenge::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_id' => Template::factory(),
            'part_key' => 'alto',
            'token_hash' => hash('sha256', Str::random(40)),
        ];
    }
}
