<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateRecording>
 */
final class TemplateRecordingFactory extends Factory
{
    protected $model = TemplateRecording::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'template_id' => Template::factory(),
            'user_id' => fn (array $recording): int => (int) (Template::query()->whereKey($recording['template_id'])->value('owner_id') ?? User::factory()->create()->id),
            'part_key' => 'alto',
            'status' => TemplateRecording::READY,
            'sample_rate' => 48000,
            'frames' => 48000 * 10,
            'offset_frames' => 0,
            'upload_bytes' => 48000 * 20 + 44,
            'sha256' => hash('sha256', fake()->uuid()),
            'upload_key' => 'uploads/recordings/'.Str::lower(Str::random(32)).'.wav',
            'file_key' => 'recordings/'.Str::lower(Str::random(32)).'.m4a',
            'stored_bytes' => 160000,
            'ready_at' => now(),
        ];
    }
}
