<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\BetaAssignment;
use App\Models\BetaSubmission;
use App\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BetaSubmission>
 */
final class BetaSubmissionFactory extends Factory
{
    protected $model = BetaSubmission::class;

    public function definition(): array
    {
        return [
            'beta_assignment_id' => BetaAssignment::factory(),
            'template_version_id' => TemplateVersion::factory(),
            'part_key' => 'alto',
            'status' => SubmissionStatus::Submitted,
            'music_key' => 'G',
            'sample_rate' => 48000,
            'frames' => 48000 * 40,
            'latency_frames' => 960,
            'bytes' => 48000 * 40 * 2 + 44,
            'sha256' => hash('sha256', fake()->uuid()),
            'device' => 'iPhone, Safari 26',
            'mic' => 'EarPods',
            'scores' => ['pitchPercent' => 92],
            'master_key' => 'beta/masters/'.fake()->uuid().'.wav',
        ];
    }
}
