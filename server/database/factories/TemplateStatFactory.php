<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplateStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateStat>
 */
final class TemplateStatFactory extends Factory
{
    protected $model = TemplateStat::class;

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'finishers' => 25,
            'also_learned' => [],
            'computed_at' => now(),
        ];
    }
}
