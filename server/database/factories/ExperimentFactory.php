<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Experiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experiment>
 */
final class ExperimentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'test-'.fake()->unique()->numberBetween(1, 99999),
            'what' => 'A test',
            'hypothesis' => 'B is better.',
            'arms' => [['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]],
            'metric' => 'f_lesson3',
            'guardrails' => ['f_lesson2'],
            'audience' => ['new' => true],
            'excludes' => [],
            'planned_per_arm' => 1500,
        ];
    }
}
