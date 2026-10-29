<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CourseClip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClip>
 */
final class CourseClipFactory extends Factory
{
    protected $model = CourseClip::class;

    public function definition(): array
    {
        return [
            'slot' => 'vibrato.'.fake()->unique()->slug(2),
            'course_id' => 'vibrato',
            'lesson_id' => 'vib-1-hear',
            'step' => 0,
            'brief' => ['sing' => ['en' => 'One held "ah" with your own easy vibrato'], 'pitch' => ['step' => 0], 'seconds' => 3.0, 'style' => 'vibrato', 'voices' => 'any', 'used' => ['vib-1-hear']],
        ];
    }
}
