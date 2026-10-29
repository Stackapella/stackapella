<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectInvite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectInvite>
 */
final class ProjectInviteFactory extends Factory
{
    protected $model = ProjectInvite::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => fn (array $invite): int => (int) Project::query()->whereKey($invite['project_id'])->value('user_id'),
            'token_hash' => hash('sha256', Str::random(32)),
            'part_key' => null,
            'part_name' => null,
            'expires_at' => now()->addDays(14),
        ];
    }
}
