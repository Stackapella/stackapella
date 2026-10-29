<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachApplication>
 */
final class CoachApplicationFactory extends Factory
{
    protected $model = CoachApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'answers' => ['experience' => 'Twelve years teaching choirs.', 'training' => 'Vocal pedagogy.', 'voiceTypes' => ['alto'], 'styles' => ['choral'], 'languages' => ['en'], 'timezone' => 'Europe/London', 'introUrl' => null],
            'sample_review' => ['comments' => [['atSeconds' => 3.5, 'noteIndex' => null, 'text' => 'Nice start.', 'mediaId' => null]], 'summary' => ['working' => 'Warm tone.', 'toWorkOn' => ['Breath', 'Pitch on the top note'], 'practise' => 'Slow scales.'], 'exercises' => [], 'demoMediaId' => null],
            'agreement_version' => 1,
            'status' => CoachApplication::SUBMITTED,
        ];
    }
}
