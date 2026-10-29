<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSuppression>
 */
final class EmailSuppressionFactory extends Factory
{
    protected $model = EmailSuppression::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'reason' => 'permanent_bounce',
            'suppressed_at' => now(),
        ];
    }
}
