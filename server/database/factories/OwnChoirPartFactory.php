<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnChoirPart>
 */
final class OwnChoirPartFactory extends Factory
{
    protected $model = OwnChoirPart::class;

    public function definition(): array
    {
        $frames = 48000 * 30;

        return [
            'own_choir_request_id' => OwnChoirRequest::factory(),
            'part_key' => 'soprano',
            'status' => 'ready',
            'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
            'media_key' => 'choir/parts/'.Str::lower(Str::random(32)).'.m4a',
            'sample_rate' => 48000,
            'frames' => $frames,
            'bytes' => 44 + $frames * 2,
            'sha256' => str_repeat('b', 64),
            'checks' => ['pitchMaxCents' => 20.0, 'pitchMedianCents' => 8.0, 'notesMeasured' => 40, 'notesTotal' => 40, 'notesOver15' => 1, 'notesOver30' => 0, 'dropouts' => 0, 'heldSeconds' => 10.0, 'durationSeconds' => 30.0],
            'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('1', 40), 'weightsRevision' => str_repeat('2', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'own-clip'],
        ];
    }
}
