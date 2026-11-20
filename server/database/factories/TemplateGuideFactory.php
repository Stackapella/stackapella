<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GuideSource;
use App\Models\AiVoice;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateGuide>
 */
final class TemplateGuideFactory extends Factory
{
    protected $model = TemplateGuide::class;

    public function definition(): array
    {
        return [
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'source' => GuideSource::Recorded,
            'ai_voice_id' => null,
            'beta_submission_id' => null,
            'is_default' => true,
            'media_key' => 'guides/'.fake()->uuid().'.m4a',
            'sample_rate' => 48000,
            'frames' => 48000 * 40,
            'offset_frames' => 0,
            'gain' => '1.000',
            'published_at' => now(),
        ];
    }

    /**
     * An AI guide (docs/ai-voices-build-plan.md), sung by the given voice, or by a new one based on `$basedOn` (with its
     * consent recorded and its singer credited, so it may leave Admin named when the ai_voices switch is on). Pass
     * `basedOn: null` for a voice not based on a named person.
     */
    public function ai(?AiVoice $voice = null, ?string $basedOn = 'Ada E.'): self
    {
        return $this->state(fn (): array => [
            'source' => GuideSource::Ai,
            'ai_voice_id' => $voice !== null ? $voice->id : AiVoice::factory()->consented()->credited()->state(['based_on' => $basedOn]),
            'is_default' => true,
            'beta_submission_id' => null,
            'approved_at' => now(),
        ]);
    }

    /** An AI guide still waiting for Hillel's review (phase 1c). */
    public function pendingReview(): self
    {
        return $this->state(['approved_at' => null]);
    }
}
