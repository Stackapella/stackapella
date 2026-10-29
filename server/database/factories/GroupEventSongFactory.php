<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupEventSong;
use App\Models\GroupSong;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GroupEventSong>
 */
final class GroupEventSongFactory extends Factory
{
    protected $model = GroupEventSong::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'es_event_id' => 'Ev'.Str::random(6),
            'group_song_id' => GroupSong::factory(),
        ];
    }
}
