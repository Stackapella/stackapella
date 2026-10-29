<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HarmonyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A finished mix someone shares (docs/backend-plan.md D6): rendered on the device, checked and
 * encoded by ProcessHarmony, heard at /h/<public_id> while it's unlisted. Its AAC and card are kept in
 * `private`, and copied to `media` only while the page is live (App\Support\Sharing\HarmonyFiles).
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int|null $project_id
 * @property int|null $part_id
 * @property string|null $part_name
 * @property string $change_id
 * @property string $title
 * @property string $visibility
 * @property string $status
 * @property string|null $failure
 * @property int $sample_rate
 * @property int $channels
 * @property int $frames
 * @property int $upload_bytes
 * @property string $sha256
 * @property string $upload_key
 * @property string|null $audio_key
 * @property string|null $card_key
 * @property string|null $media_audio_key
 * @property string|null $media_card_key
 * @property int|null $stored_bytes
 * @property list<float>|null $peaks
 * @property list<array{name: string, color: string, peaks: string, kind?: string}> $tracks
 * @property list<int>|null $singer_ids
 * @property list<int>|null $take_ids
 * @property list<string>|null $open_parts
 * @property bool $open_parts_guide
 * @property Carbon|null $stems_ready_at
 * @property int|null $parent_id
 * @property string|null $fill_part_key
 * @property Carbon|null $fill_told_at
 * @property int|null $featured_fill_id
 * @property list<int> $guide_ids
 * @property int|null $template_version_id
 * @property string|null $words_part_key
 * @property string|null $bpm
 * @property int|null $beat_zero_ms
 * @property Carbon|null $title_held_at
 * @property Carbon|null $hidden_at
 * @property string|null $hidden_reason
 * @property Carbon|null $removed_at
 * @property Carbon|null $may_feature_at
 * @property Carbon|null $gallery_opt_in_at
 * @property int $sign_ups
 * @property Carbon|null $ready_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Project|null $project
 * @property-read Part|null $part
 * @property-read TemplateVersion|null $templateVersion
 */
#[UseFactory(HarmonyFactory::class)]
final class Harmony extends Model
{
    /** @use HasFactory<HarmonyFactory> */
    use HasFactory;

    public const PRIVATE = 'private';

    public const UNLISTED = 'unlisted';

    public const UPLOADING = 'uploading';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /** Why a page was hidden: 3 reports, a copyright notice, or Hillel's call. */
    public const HIDDEN_REPORTS = 'reports';

    public const HIDDEN_DMCA = 'dmca';

    public const HIDDEN_ADMIN = 'admin';

    protected $table = 'harmonies';

    protected $fillable = [
        'title',
        'sample_rate',
        'channels',
        'frames',
        'upload_bytes',
        'sha256',
    ];

    /**
     * Its id everywhere (the link /h/<id>, the API): 12 random letters and digits, never a Sqid,
     * since for an unlisted harmony the id is the only secret (docs/decisions.md).
     */
    protected static function booted(): void
    {
        self::creating(static function (Harmony $harmony): void {
            $harmony->public_id ??= Str::random(12);
        });
    }

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'channels' => 'integer',
            'frames' => 'integer',
            'upload_bytes' => 'integer',
            'stored_bytes' => 'integer',
            'peaks' => 'array',
            'tracks' => 'array',
            'singer_ids' => 'array',
            'take_ids' => 'array',
            'open_parts' => 'array',
            'open_parts_guide' => 'boolean',
            'stems_ready_at' => 'datetime',
            'fill_told_at' => 'datetime',
            'guide_ids' => 'array',
            'beat_zero_ms' => 'integer',
            'sign_ups' => 'integer',
            'title_held_at' => 'datetime',
            'hidden_at' => 'datetime',
            'removed_at' => 'datetime',
            'may_feature_at' => 'datetime',
            'gallery_opt_in_at' => 'datetime',
            'ready_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function durationSeconds(): float
    {
        return round($this->frames / max(1, $this->sample_rate), 3);
    }

    /**
     * null, under_review, hidden or removed, as the owner sees it (contract/api.md).
     */
    public function moderation(): ?string
    {
        return match (true) {
            $this->removed_at !== null => 'removed',
            $this->hidden_at !== null => $this->hidden_reason === self::HIDDEN_REPORTS ? 'under_review' : 'hidden',
            $this->user->isSuspended() => 'hidden',
            default => null,
        };
    }

    /**
     * Anyone with the link may hear it now.
     */
    public function isLive(): bool
    {
        return $this->deleted_at === null && $this->status === self::READY && $this->visibility === self::UNLISTED && $this->moderation() === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /**
     * @return HasMany<ShareVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(ShareVideo::class);
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * The original a fill was sung for (N5).
     *
     * @return BelongsTo<Harmony, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Harmony, $this>
     */
    public function featuredFill(): BelongsTo
    {
        return $this->belongsTo(self::class, 'featured_fill_id');
    }

    /**
     * @return HasMany<HarmonyStem, $this>
     */
    public function stems(): HasMany
    {
        return $this->hasMany(HarmonyStem::class);
    }
}
