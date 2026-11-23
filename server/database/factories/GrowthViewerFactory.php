<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GrowthViewer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrowthViewer>
 */
final class GrowthViewerFactory extends Factory
{
    protected $model = GrowthViewer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->userName().'@example.com',
            'google_id' => null,
            'granted_at' => now(),
            'revoked_at' => null,
        ];
    }
}
