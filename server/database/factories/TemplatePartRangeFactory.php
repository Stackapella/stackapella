<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplatePartRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplatePartRange>
 */
final class TemplatePartRangeFactory extends Factory
{
    protected $model = TemplatePartRange::class;

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'part_key' => 'alto',
            'low' => 55,
            'high' => 69,
        ];
    }
}
