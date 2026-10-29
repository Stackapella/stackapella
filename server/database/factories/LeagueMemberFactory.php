<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeagueMember>
 */
final class LeagueMemberFactory extends Factory
{
    protected $model = LeagueMember::class;

    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'xp' => 0,
        ];
    }
}
