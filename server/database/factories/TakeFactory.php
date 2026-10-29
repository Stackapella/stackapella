<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Take>
 */
final class TakeFactory extends Factory
{
    protected $model = Take::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'part_id' => Part::factory(),
            'project_id' => fn (array $take): int => (int) Part::query()->whereKey($take['part_id'])->value('project_id'),
            'user_id' => fn (array $take): int => (int) Project::query()->whereKey($take['project_id'])->value('user_id'),
            'status' => Take::READY,
            'failure' => null,
            'sample_rate' => 48000,
            'frames' => 48000 * 10,
            'offset_frames' => 0,
            'upload_bytes' => 48000 * 10 * 2 + 44,
            'stored_bytes' => 600000,
            'sha256' => hash('sha256', fake()->uuid()),
            'frames_per_peak' => 512,
            'peaks' => [0.1, 0.2],
            'upload_key' => 'uploads/takes/'.Str::lower(Str::random(32)).'.wav',
            'file_key' => 'takes/'.Str::lower(Str::random(32)).'.flac',
            'ready_at' => now(),
            'seq' => 1,
        ];
    }

    public function uploading(): static
    {
        return $this->state(fn (): array => ['status' => Take::UPLOADING, 'stored_bytes' => null, 'peaks' => null, 'file_key' => null, 'ready_at' => null]);
    }

    public function binned(): static
    {
        return $this->state(fn (): array => ['binned_at' => now()]);
    }
}
