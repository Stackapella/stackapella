<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Engagement>
 */
final class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    public function definition(): array
    {
        return [
            'kind' => Engagement::REVIEW,
            'singer_id' => User::factory(),
            'coach_id' => User::factory(),
            'status' => Engagement::REQUESTED,
            'price_cents' => 2500,
            'currency' => 'gbp',
            'fee_cents' => 375,
            'turnaround_days' => 5,
            'stripe_payment_intent' => 'pi_'.Str::lower(Str::random(16)),
            'stripe_charge' => 'ch_'.Str::lower(Str::random(16)),
            'accept_by' => now()->addHours(48),
        ];
    }
}
