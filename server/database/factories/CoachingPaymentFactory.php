<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachingPayment;
use App\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CoachingPayment>
 */
final class CoachingPaymentFactory extends Factory
{
    protected $model = CoachingPayment::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'kind' => CoachingPayment::SALE,
            'ref' => 'sale:'.Str::random(10),
            'cents' => 2500,
            'fee_cents' => 375,
            'currency' => 'gbp',
            'description' => 'A take review',
            'paid_at' => now(),
        ];
    }
}
