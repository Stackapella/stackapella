<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportMessage;
use App\Models\SupportReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportReply>
 */
final class SupportReplyFactory extends Factory
{
    protected $model = SupportReply::class;

    public function definition(): array
    {
        return [
            'support_message_id' => SupportMessage::factory(),
            'body' => 'Thanks: try the timing check once more with headphones.',
        ];
    }
}
