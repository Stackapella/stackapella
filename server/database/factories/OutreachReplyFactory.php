<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OutreachReply;
use App\Models\OutreachTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachReply>
 */
final class OutreachReplyFactory extends Factory
{
    protected $model = OutreachReply::class;

    public function definition(): array
    {
        return [
            'outreach_target_id' => OutreachTarget::factory(),
            'from_email' => fake()->userName().'@example.com',
            'subject' => 'Re: Part practice for your members',
            'body' => 'Thanks, tell me more.',
            'received_at' => now(),
        ];
    }
}
