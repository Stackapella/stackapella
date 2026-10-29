<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Harmony;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'harmony_id' => Harmony::factory(),
            'user_id' => User::factory(),
            'body' => 'Lovely blend on the last verse.',
        ];
    }
}
