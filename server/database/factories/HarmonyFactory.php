<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Harmony;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Harmony>
 */
final class HarmonyFactory extends Factory
{
    protected $model = Harmony::class;

    public function definition(): array
    {
        $frames = 48000 * 30;

        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $harmony): int => $harmony['project_id'] !== null ? (int) Project::query()->whereKey($harmony['project_id'])->value('user_id') : User::factory()->create()->id,
            'part_id' => null,
            'change_id' => (string) Str::uuid(),
            'title' => 'Silent Night for Mum',
            'visibility' => Harmony::UNLISTED,
            'status' => Harmony::READY,
            'failure' => null,
            'sample_rate' => 48000,
            'channels' => 2,
            'frames' => $frames,
            'upload_bytes' => 44 + $frames * 4,
            'sha256' => hash('sha256', fake()->uuid()),
            'upload_key' => 'uploads/harmonies/'.Str::lower(Str::random(32)).'.wav',
            'audio_key' => 'harmonies/'.Str::lower(Str::random(32)).'.m4a',
            'card_key' => 'harmonies/'.Str::lower(Str::random(32)).'.jpg',
            'stored_bytes' => 480000,
            'peaks' => array_fill(0, 600, 0.5),
            'tracks' => [
                ['name' => 'Soprano', 'color' => '#e4a94b', 'peaks' => base64_encode(str_repeat(chr(180), 600))],
                ['name' => 'Alto', 'color' => '#6fa8dc', 'peaks' => base64_encode(str_repeat(chr(120), 600))],
            ],
            'guide_ids' => [],
            'ready_at' => now(),
        ];
    }

    public function uploading(): static
    {
        return $this->state(fn (): array => ['status' => Harmony::UPLOADING, 'audio_key' => null, 'card_key' => null, 'stored_bytes' => null, 'peaks' => null, 'ready_at' => null]);
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['visibility' => Harmony::PRIVATE]);
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => ['hidden_at' => now(), 'hidden_reason' => Harmony::HIDDEN_REPORTS]);
    }
}
