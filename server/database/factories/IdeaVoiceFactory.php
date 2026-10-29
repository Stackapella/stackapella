<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdeaVoice>
 */
final class IdeaVoiceFactory extends Factory
{
    protected $model = IdeaVoice::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'idea_id' => Idea::factory(),
            'user_id' => User::factory(),
            'part_key' => 'alto',
            'status' => IdeaVoice::READY,
            'sample_rate' => 48000,
            'frames' => 480000,
            'offset_frames' => 0,
            'upload_bytes' => 960044,
            'sha256' => str_repeat('a', 64),
            'upload_key' => 'uploads/voices/'.Str::lower(Str::random(32)).'.wav',
            'file_key' => 'voices/'.Str::lower(Str::random(32)).'.m4a',
            'stored_bytes' => 120000,
            'frames_per_peak' => 512,
            'peaks' => [0.1, 0.5, 0.3],
            'rights_confirmed_at' => now(),
            'ready_at' => now(),
        ];
    }
}
