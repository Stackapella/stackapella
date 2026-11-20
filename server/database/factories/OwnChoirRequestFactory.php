<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnChoirRequest>
 */
final class OwnChoirRequestFactory extends Factory
{
    protected $model = OwnChoirRequest::class;

    public function definition(): array
    {
        $frames = 48000 * 20;

        return [
            'user_id' => User::factory(),
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'offset_frames' => 0,
            'status' => OwnChoirStatus::Uploading,
            'upload_key' => 'uploads/choir/'.Str::lower(Str::random(32)).'.wav',
            'sample_rate' => 48000,
            'frames' => $frames,
            'bytes' => 44 + $frames * 2,
            'sha256' => str_repeat('a', 64),
        ];
    }
}
