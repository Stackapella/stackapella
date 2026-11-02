<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SchoolRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolRedemption>
 */
final class SchoolRedemptionFactory extends Factory
{
    protected $model = SchoolRedemption::class;

    public function definition(): array
    {
        return ['school_class_id' => SchoolClass::factory(), 'user_id' => User::factory(), 'plus_until' => now()->addYear()];
    }
}
