<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiUploadStatus;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiUpload>
 */
final class AiUploadFactory extends Factory
{
    protected $model = AiUpload::class;

    public function definition(): array
    {
        $frames = 48000 * 30;

        return [
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'ai_voice_id' => AiVoice::factory(),
            'status' => AiUploadStatus::Uploading,
            'failure' => null,
            'upload_key' => 'uploads/ai/'.Str::lower(Str::random(32)).'.wav',
            'master_key' => null,
            'sample_rate' => 48000,
            'frames' => $frames,
            'bytes' => 44 + $frames * 2,
            'sha256' => str_repeat('a', 64),
            'checks' => ['pitchMaxCents' => 20.0, 'pitchMedianCents' => 8.0, 'notesMeasured' => 41, 'notesTotal' => 41, 'notesOver15' => 2, 'notesOver30' => 0, 'dropouts' => 1, 'heldSeconds' => 20.0, 'durationSeconds' => 30.0],
            'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('1', 40), 'weightsRevision' => str_repeat('2', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'test-alto'],
        ];
    }
}
