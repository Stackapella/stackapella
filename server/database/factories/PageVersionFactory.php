<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageVersion>
 */
final class PageVersionFactory extends Factory
{
    protected $model = PageVersion::class;

    public function definition(): array
    {
        return ['path' => '/'.fake()->unique()->slug(), 'content_hash' => hash('sha256', fake()->sentence()), 'changed_at' => now()];
    }
}
