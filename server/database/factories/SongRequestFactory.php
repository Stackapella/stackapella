<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SongRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongRequest>
 */
final class SongRequestFactory extends Factory
{
    protected $model = SongRequest::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => ucfirst($title),
            'match_key' => SongRequest::matchKey($title),
            'status' => 'requested',
        ];
    }
}
