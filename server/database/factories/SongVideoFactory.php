<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SongVideoHarmony;
use App\Enums\SongVideoStatus;
use App\Models\SongVideo;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SongVideo>
 */
final class SongVideoFactory extends Factory
{
    protected $model = SongVideo::class;

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'template_version' => 1,
            'video_id' => Str::random(11),
            'status' => SongVideoStatus::Draft,
            'hidden_reason' => null,
            'title' => 'A choir sings it',
            'channel_title' => 'A choir',
            'duration_seconds' => 180,
            'checked_at' => now(),
            'form' => [['kind' => 'section', 'section' => 0, 'shift' => 0, 'cents' => 0]],
            'anchors' => [[2.0, 0.0], [5.0, 3.0]],
            'harmony' => SongVideoHarmony::Same,
            'other_words' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => ['status' => SongVideoStatus::Approved, 'approved_at' => now()]);
    }
}
