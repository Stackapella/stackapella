<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'title' => 'My harmony',
            'bpm' => '90.00',
            'music_key' => 'G',
            'time_signature' => '4/4',
            'template_slug' => null,
            'template_version' => null,
            'settings' => [],
            'version' => 1,
            'seq' => 1,
        ];
    }

    public function binned(): static
    {
        return $this->state(fn (): array => ['binned_at' => now()]);
    }
}
