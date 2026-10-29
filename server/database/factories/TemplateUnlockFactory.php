<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplateUnlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateUnlock>
 */
final class TemplateUnlockFactory extends Factory
{
    protected $model = TemplateUnlock::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'template_id' => Template::factory()];
    }
}
