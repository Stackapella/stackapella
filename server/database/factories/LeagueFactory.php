<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\League;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<League>
 */
final class LeagueFactory extends Factory
{
    protected $model = League::class;

    public function definition(): array
    {
        return [
            'week' => now()->startOfWeek()->toDateString(),
            'tier' => 1,
        ];
    }
}
