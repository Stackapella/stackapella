<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachMessage;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachMessage>
 */
final class CoachMessageFactory extends Factory
{
    protected $model = CoachMessage::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'sender_id' => User::factory(),
            'body' => 'Send me the last verse when you can.',
        ];
    }
}
