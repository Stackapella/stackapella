<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TemplateAccess;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
final class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(3),
            'access' => TemplateAccess::Plus,
            'free_from' => null,
            'free_until' => null,
        ];
    }
}
