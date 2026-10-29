<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChoirVoice>
 */
final class ChoirVoiceFactory extends Factory
{
    protected $model = ChoirVoice::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'choir_id' => Choir::factory(),
            'user_id' => User::factory(),
            'part_key' => 'alto',
            'status' => ChoirVoice::READY,
            'credit' => 'handle',
            'score' => 80,
            'file_key' => 'choirs/'.Str::lower(Str::random(32)).'.flac',
            'sample_rate' => 48000,
            'frames' => 96000,
            'rights_confirmed_at' => now(),
        ];
    }
}
