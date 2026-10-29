<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Harmony;
use App\Models\ShareVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareVideo>
 */
final class ShareVideoFactory extends Factory
{
    protected $model = ShareVideo::class;

    public function definition(): array
    {
        return [
            'harmony_id' => Harmony::factory(),
            'user_id' => fn (array $video): int => (int) Harmony::query()->whereKey($video['harmony_id'])->value('user_id'),
            'start_ms' => 0,
            'end_ms' => 20000,
            'look' => 'dark',
            'words' => true,
            'status' => ShareVideo::QUEUED,
            'progress' => 0,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => ['status' => ShareVideo::READY, 'progress' => 1, 'file_key' => 'exports/videos/'.Str::lower(Str::random(32)).'.mp4', 'bytes' => 2_000_000, 'started_at' => now(), 'expires_at' => now()->addDays(7)]);
    }
}
