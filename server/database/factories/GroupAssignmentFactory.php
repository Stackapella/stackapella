<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GroupAssignment;
use App\Models\GroupSong;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupAssignment>
 */
final class GroupAssignmentFactory extends Factory
{
    protected $model = GroupAssignment::class;

    public function definition(): array
    {
        return [
            'group_song_id' => GroupSong::factory(),
            'user_id' => User::factory(),
            'part_key' => 'alto',
        ];
    }
}
