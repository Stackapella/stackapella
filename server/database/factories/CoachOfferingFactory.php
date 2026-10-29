<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachOffering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachOffering>
 */
final class CoachOfferingFactory extends Factory
{
    protected $model = CoachOffering::class;

    public function definition(): array
    {
        return [
            'coach_id' => User::factory(),
            'kind' => CoachOffering::REVIEW,
            'title' => 'A take review',
            'description' => 'I listen to your take and pin notes where it matters.',
            'price_cents' => 2500,
            'currency' => 'gbp',
            'turnaround_days' => 5,
            'max_seconds' => 180,
            'reviews_per_month' => null,
        ];
    }
}
