<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasSqid;
use Database\Factories\BetaAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A part Hillel asked a singer to record: one template's part (in its current version).
 *
 * @property int $id
 * @property int $beta_singer_id
 * @property int|null $template_id
 * @property string|null $part_key
 * @property int|null $course_clip_id
 * @property int|null $minutes
 * @property Carbon|null $claimed_at Lend your voice: a volunteer took it themselves
 * @property Carbon|null $expires_at held until then, released if nothing was sent
 * @property Carbon|null $asked_again_at Hillel asked for this part again (the song changed)
 * @property string|null $asked_again_note what he said
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BetaSinger $singer
 * @property-read Template|null $template
 * @property-read CourseClip|null $clip
 * @property-read BetaSubmission|null $latestSubmission
 */
#[UseFactory(BetaAssignmentFactory::class)]
final class BetaAssignment extends Model
{
    /** @use HasFactory<BetaAssignmentFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'template_id',
        'part_key',
        'minutes',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'asked_again_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BetaSinger, $this>
     */
    public function singer(): BelongsTo
    {
        return $this->belongsTo(BetaSinger::class, 'beta_singer_id');
    }

    /**
     * A course clip to record instead of a song's part.
     *
     * @return BelongsTo<CourseClip, $this>
     */
    public function clip(): BelongsTo
    {
        return $this->belongsTo(CourseClip::class, 'course_clip_id');
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<BetaSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(BetaSubmission::class);
    }

    /**
     * @return HasOne<BetaSubmission, $this>
     */
    public function latestSubmission(): HasOne
    {
        // A take still uploading (perhaps abandoned) doesn't hide the latest real one.
        return $this->hasOne(BetaSubmission::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('status', '!=', SubmissionStatus::Uploading->value));
    }

    /**
     * Is a take of this part the default real voice on the template's current version?
     * Needs submissions.guide and template loaded.
     */
    /**
     * Asked to record this part again, with no take sent since: My parts shows it as a retake.
     */
    public function askedAgain(): bool
    {
        $latest = $this->latestSubmission;

        return $this->asked_again_at !== null && ($latest === null || $latest->created_at === null || $latest->created_at->lt($this->asked_again_at));
    }

    public function isTheVoice(): bool
    {
        foreach ($this->submissions as $submission) {
            $guide = $submission->guide;

            if ($guide !== null && $guide->is_default && $guide->template_version_id === $this->template?->current_version_id) {
                return true;
            }
        }

        return false;
    }
}
