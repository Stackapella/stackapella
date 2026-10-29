<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\EngagementReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngagementReport>
 */
final class EngagementReportFactory extends Factory
{
    protected $model = EngagementReport::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'user_id' => User::factory(),
            'text' => 'The review never came.',
        ];
    }
}
