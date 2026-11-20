<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiVoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiVoice>
 */
final class AiVoiceFactory extends Factory
{
    protected $model = AiVoice::class;

    public function definition(): array
    {
        return [
            'name' => 'Alto '.fake()->randomLetter(),
            'based_on' => fake()->firstName().' '.fake()->randomLetter().'.',
            'consent_date' => null,
            'consent_version' => null,
            'payment_noted' => false,
            'credited' => false,
            'withdrawn_at' => null,
        ];
    }

    /** Its singer's consent recorded: what it sings may leave Admin. */
    public function consented(): self
    {
        return $this->state(['consent_date' => now()->toDateString(), 'consent_version' => 'ai-voice-1']);
    }

    /** Its singer chose to be named on the label. */
    public function credited(): self
    {
        return $this->state(['credited' => true]);
    }

    public function withdrawn(): self
    {
        return $this->state(['withdrawn_at' => now()]);
    }
}
