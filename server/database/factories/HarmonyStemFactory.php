<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Harmony;
use App\Models\HarmonyStem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HarmonyStem>
 */
final class HarmonyStemFactory extends Factory
{
    protected $model = HarmonyStem::class;

    public function definition(): array
    {
        return [
            'harmony_id' => Harmony::factory(),
            'part_key' => 'alto',
            'file_key' => 'stems/'.Str::lower(Str::random(32)).'.flac',
            'stored_bytes' => 100000,
            'sample_rate' => 48000,
            'frames' => 96000,
        ];
    }
}
