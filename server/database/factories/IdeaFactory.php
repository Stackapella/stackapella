<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Idea;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Idea>
 */
final class IdeaFactory extends Factory
{
    protected $model = Idea::class;

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'user_id' => User::factory(),
            'note' => 'Hummed on the bus. Who wants the bass?',
            'voicing' => 4,
            'part_keys' => ['soprano', 'alto', 'tenor', 'bass'],
            'rights_confirmed_at' => now(),
        ];
    }
}
