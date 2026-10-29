<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SongRequest;
use App\Models\SongRequestVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongRequestVote>
 */
final class SongRequestVoteFactory extends Factory
{
    protected $model = SongRequestVote::class;

    public function definition(): array
    {
        return [
            'song_request_id' => SongRequest::factory(),
            'user_id' => User::factory(),
        ];
    }
}
