<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplateRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateRating>
 */
final class TemplateRatingFactory extends Factory
{
    protected $model = TemplateRating::class;

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'user_id' => User::factory(),
            'stars' => fake()->numberBetween(1, 5),
        ];
    }
}
