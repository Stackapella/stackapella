<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OutreachSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachSuppression>
 */
final class OutreachSuppressionFactory extends Factory
{
    protected $model = OutreachSuppression::class;

    public function definition(): array
    {
        return ['email' => fake()->unique()->userName().'@example.com', 'reason' => 'no_thanks'];
    }
}
