<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TeacherLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeacherLink>
 */
final class TeacherLinkFactory extends Factory
{
    protected $model = TeacherLink::class;

    public function definition(): array
    {
        return [
            'singer_id' => User::factory(),
            'name' => 'Ms Okafor',
            'token_hash' => hash('sha256', Str::random(24)),
            'status' => TeacherLink::PENDING,
        ];
    }
}
