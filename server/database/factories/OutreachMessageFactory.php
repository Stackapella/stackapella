<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OutreachMessage;
use App\Models\OutreachTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachMessage>
 */
final class OutreachMessageFactory extends Factory
{
    protected $model = OutreachMessage::class;

    public function definition(): array
    {
        return [
            'outreach_target_id' => OutreachTarget::factory(),
            'kind' => OutreachMessage::FIRST,
            'subject' => 'Part practice for your members',
            'body' => "Hello,\n\nA short note.\n\nHillel",
            'status' => 'draft',
        ];
    }

    public function sent(?\DateTimeInterface $at = null): self
    {
        return $this->state(fn (array $attributes): array => ['status' => 'sent', 'sent_at' => $at ?? now(), 'sent_via' => 'email', 'to_email' => fake()->userName().'@example.com']);
    }
}
