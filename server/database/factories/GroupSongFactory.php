<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupSong;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupSong>
 */
final class GroupSongFactory extends Factory
{
    protected $model = GroupSong::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'template_id' => Template::factory(),
        ];
    }
}
