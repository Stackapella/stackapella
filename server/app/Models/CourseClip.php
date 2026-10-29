<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\CourseClipFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A short real-voice clip a course step plays (web/course/plus/clips.json), recorded by a beta
 * singer like a part and published once accepted (App\Actions\Courses\CourseClips).
 *
 * @property int $id
 * @property string $slot
 * @property string $course_id
 * @property string|null $lesson_id
 * @property int|null $step
 * @property array{sing: array<string, string>, pitch: array<string, int>|null, seconds: float, style: string|null, voices: string|list<string>, used: list<string>} $brief
 * @property Carbon|null $retired_at
 * @property int|null $beta_submission_id
 * @property string|null $media_key
 * @property int|null $sample_rate
 * @property int|null $frames
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BetaSubmission|null $submission
 */
#[UseFactory(CourseClipFactory::class)]
final class CourseClip extends Model
{
    /** @use HasFactory<CourseClipFactory> */
    use HasFactory;

    use HasSqid;

    /** Server-owned throughout: set from clips.json and by publishing (forceFill). */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'brief' => 'array',
            'step' => 'integer',
            'retired_at' => 'datetime',
            'published_at' => 'datetime',
            'sample_rate' => 'integer',
            'frames' => 'integer',
        ];
    }

    /**
     * The accepted take it was published from.
     *
     * @return BelongsTo<BetaSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BetaSubmission::class, 'beta_submission_id');
    }
}
