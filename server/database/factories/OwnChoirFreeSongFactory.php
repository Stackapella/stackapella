<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnChoirFreeSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnChoirFreeSong>
 */
final class OwnChoirFreeSongFactory extends Factory
{
    protected $model = OwnChoirFreeSong::class;

    public function definition(): array
    {
        return ['key_hash' => hash('sha256', (string) fake()->unique()->uuid())];
    }
}
