<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Flag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flag>
 */
final class FlagFactory extends Factory
{
    protected $model = Flag::class;

    public function definition(): array
    {
        return [
            'name' => 'read_only',
            'value' => 'on',
            'reason' => 'An incident',
        ];
    }
}
