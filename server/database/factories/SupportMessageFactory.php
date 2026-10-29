<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
final class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'category' => 'recording',
            'body' => 'My takes come out late on my phone.',
            'status' => SupportMessage::OPEN,
        ];
    }
}
