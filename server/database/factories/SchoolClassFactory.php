<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SchoolTeacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
final class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= SchoolClass::ALPHABET[random_int(0, strlen(SchoolClass::ALPHABET) - 1)];
        }

        return ['teacher_id' => User::factory(), 'name' => 'Period 3 choir', 'code' => $code, 'seats' => 30, 'expires_at' => now()->addDays(120)];
    }

    /** An approved teacher unless one's already on record (a test that needs pending says so). */
    public function configure(): static
    {
        return $this->afterCreating(function (SchoolClass $class): void {
            if ($class->teacher_id !== null && ! SchoolTeacher::query()->where('user_id', $class->teacher_id)->exists()) {
                (new SchoolTeacher)->forceFill(['user_id' => $class->teacher_id, 'approved_at' => now(), 'seat_allowance' => SchoolTeacher::ALLOWANCE])->save();
            }
        });
    }
}
