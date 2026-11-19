<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SongVideoHarmony;
use App\Enums\SongVideoHidden;
use App\Enums\SongVideoStatus;
use App\Models\Concerns\HasSqid;
use Database\Factories\SongVideoFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A real recording on YouTube synced to one of our songs (docs/youtube-sync-plan.md): our sync map (the form and the
 * anchors, made by a person, ours) plus YouTube's video id and what its last check said. The check's fields, the
 * status and who approved it are server-owned, set with forceFill by SaveSongVideo, ApproveSongVideo and
 * RecheckSongVideos.
 *
 * @property int $id
 * @property int $template_id
 * @property int $template_version
 * @property string $video_id
 * @property SongVideoStatus $status
 * @property SongVideoHidden|null $hidden_reason
 * @property string $title
 * @property string $channel_title
 * @property int $duration_seconds
 * @property Carbon $checked_at
 * @property list<array<string, mixed>> $form
 * @property list<array{0: float, 1: float}> $anchors
 * @property SongVideoHarmony $harmony
 * @property list<array{0: float, 1: float}>|null $other_words
 * @property int|null $synced_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 * @property-read User|null $syncer
 * @property-read User|null $approver
 */
#[UseFactory(SongVideoFactory::class)]
final class SongVideo extends Model
{
    /** @use HasFactory<SongVideoFactory> */
    use HasFactory;

    use HasSqid;

    /** Re-checked with YouTube once a row is this old, within the 30 days the policies allow. */
    public const RECHECK_DAYS = 25;

    /** Deleted once its last good check is older than this (YouTube's Developer Policies, III.E.4.d). */
    public const KEEP_DAYS = 30;

    protected $fillable = [
        'form',
        'anchors',
        'harmony',
        'other_words',
    ];

    protected function casts(): array
    {
        return [
            'status' => SongVideoStatus::class,
            'hidden_reason' => SongVideoHidden::class,
            'harmony' => SongVideoHarmony::class,
            'form' => 'array',
            'anchors' => 'array',
            'other_words' => 'array',
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * What singers see: approved, not hidden by the last check.
     *
     * @param  Builder<SongVideo>  $query
     * @return Builder<SongVideo>
     */
    public function scopeShown(Builder $query): Builder
    {
        return $query->where('status', SongVideoStatus::Approved)->whereNull('hidden_reason');
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return BelongsTo<User, $this> */
    public function syncer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
