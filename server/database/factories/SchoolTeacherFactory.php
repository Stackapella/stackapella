<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SchoolTeacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolTeacher>
 */
final class SchoolTeacherFactory extends Factory
{
    protected $model = SchoolTeacher::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'approved_at' => now(), 'seat_allowance' => SchoolTeacher::ALLOWANCE];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['approved_at' => null]);
    }
}
