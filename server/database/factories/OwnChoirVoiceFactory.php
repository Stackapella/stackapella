<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnChoirVoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnChoirVoice>
 */
final class OwnChoirVoiceFactory extends Factory
{
    protected $model = OwnChoirVoice::class;

    public function definition(): array
    {
        $frames = 24000 * 20;

        return [
            'user_id' => User::factory(),
            'status' => 'ready',
            'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
            'reference_key' => 'choir/voices/'.Str::lower(Str::random(32)).'.wav',
            'sample_rate' => 24000,
            'frames' => $frames,
            'bytes' => 44 + $frames * 2,
            'sha256' => str_repeat('c', 64),
            'meta' => ['notes' => [], 'words' => 'test'],
            'saved_at' => now(),
            'last_used_at' => now(),
        ];
    }
}
