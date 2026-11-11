<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrackScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrackScore>
 */
final class TrackScoreFactory extends Factory
{
    protected $model = TrackScore::class;

    public function definition(): array
    {
        $key = 'scores/'.Str::lower(Str::random(32)).'.pdf';

        return [
            'user_id' => User::factory(),
            'name' => 'Score.pdf',
            'kind' => 'pdf',
            'content_type' => 'application/pdf',
            'bytes' => 1024,
            'sha256' => str_repeat('a', 64),
            'status' => TrackScore::READY,
            'upload_key' => 'uploads/'.$key,
            'file_key' => $key,
        ];
    }
}
