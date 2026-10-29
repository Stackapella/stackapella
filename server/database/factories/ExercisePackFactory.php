<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExercisePack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExercisePack>
 */
final class ExercisePackFactory extends Factory
{
    protected $model = ExercisePack::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'coach_id' => User::factory(),
            'title' => 'Breath for long phrases',
            'description' => 'Five exercises.',
            'price_cents' => 0,
            'currency' => 'gbp',
            'exercises' => [['id' => 'breath-4-4', 'title' => 'Four in, four out']],
            'status' => ExercisePack::APPROVED,
            'approved_title' => 'Breath for long phrases',
            'approved_exercises' => [['id' => 'breath-4-4', 'title' => 'Four in, four out']],
            'approved_description' => fn (array $attributes): string => (string) $attributes['description'],
            'approved_price_cents' => fn (array $attributes): int => (int) $attributes['price_cents'],
            'approved_currency' => fn (array $attributes): string => (string) $attributes['currency'],
        ];
    }
}
