<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StripeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StripeEvent>
 */
final class StripeEventFactory extends Factory
{
    protected $model = StripeEvent::class;

    public function definition(): array
    {
        return ['stripe_id' => 'evt_'.Str::random(24), 'type' => 'invoice.paid'];
    }
}
