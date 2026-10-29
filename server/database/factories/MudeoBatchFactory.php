<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MudeoBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MudeoBatch>
 */
final class MudeoBatchFactory extends Factory
{
    protected $model = MudeoBatch::class;

    public function definition(): array
    {
        return [
            'count' => 50,
            'sent_at' => now(),
        ];
    }
}
