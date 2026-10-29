<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
final class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'director_id' => User::factory(),
            'name' => 'Thursday Singers',
        ];
    }
}
