<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BetaAssignment>
 */
final class BetaAssignmentFactory extends Factory
{
    protected $model = BetaAssignment::class;

    public function definition(): array
    {
        return [
            'beta_singer_id' => BetaSinger::factory(),
            'template_id' => Template::factory(),
            'part_key' => 'alto',
            'minutes' => 25,
        ];
    }
}
