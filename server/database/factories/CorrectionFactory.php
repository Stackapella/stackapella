<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CorrectionKind;
use App\Enums\CorrectionState;
use App\Models\Correction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Correction>
 */
final class CorrectionFactory extends Factory
{
    protected $model = Correction::class;

    public function definition(): array
    {
        return [
            'kind' => CorrectionKind::Fact,
            'state' => CorrectionState::New,
            'text' => fake()->sentence(12),
            'source' => null,
            'page_type' => 'song',
            'page_path' => '/songs/amazing-grace',
            'page_title' => 'Amazing Grace',
            'lang' => 'en',
        ];
    }
}
