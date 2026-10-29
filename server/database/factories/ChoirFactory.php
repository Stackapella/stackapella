<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Choir;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Choir>
 */
final class ChoirFactory extends Factory
{
    protected $model = Choir::class;

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
        ];
    }
}
