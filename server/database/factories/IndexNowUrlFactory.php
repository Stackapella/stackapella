<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IndexNowUrl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndexNowUrl>
 */
final class IndexNowUrlFactory extends Factory
{
    protected $model = IndexNowUrl::class;

    public function definition(): array
    {
        $path = '/songs/'.fake()->unique()->slug(2);

        return [
            'path_hash' => IndexNowUrl::hashOf($path),
            'path' => $path,
            'lastmod' => now()->subDays(3)->toDateString(),
            'submitted_at' => now()->subDays(3),
        ];
    }
}
