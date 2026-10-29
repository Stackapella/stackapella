<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HandleHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandleHold>
 */
final class HandleHoldFactory extends Factory
{
    protected $model = HandleHold::class;

    public function definition(): array
    {
        return ['handle' => 'held_'.fake()->unique()->numberBetween(100, 999), 'held_until' => null];
    }
}
