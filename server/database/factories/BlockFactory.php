<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Block;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
final class BlockFactory extends Factory
{
    protected $model = Block::class;

    public function definition(): array
    {
        return [
            'type' => BlockType::Email,
            'value' => fake()->unique()->safeEmail(),
            'reason' => 'Spam sign-ups',
            'expires_at' => null,
        ];
    }
}
