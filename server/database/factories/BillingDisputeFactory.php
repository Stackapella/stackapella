<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BillingDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingDispute>
 */
final class BillingDisputeFactory extends Factory
{
    protected $model = BillingDispute::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'stripe_id' => 'dp_'.Str::random(24), 'amount_cents' => 3999, 'currency' => 'usd', 'reason' => 'fraudulent', 'status' => 'needs_response'];
    }
}
