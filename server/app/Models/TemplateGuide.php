<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuideSource;
use App\Models\Concerns\HasSqid;
use App\Models\Scopes\RecordedGuides;
use App\Support\Ids;
use Database\Factories\TemplateGuideFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A voice for one part of one template version, published on the CDN (an AAC in MP4 with its
 * edit list). The catalogue lists each part's default; the rest are alternates.
 *
 * Recorded (a real singer's take) or AI (docs/ai-voices-build-plan.md): every query sees recorded
 * guides only (RecordedGuides) unless it asks with withAi(). An AI guide exists only where its part
 * has no recorded one on that version, so it's always that part's default, and it's deleted (retired)
 * the moment a recorded one is published there.
 *
 * @property int $id
 * @property int $template_version_id
 * @property string $part_key
 * @property GuideSource $source
 * @property int|null $ai_voice_id
 * @property int|null $beta_submission_id
 * @property int|null $harmony_id
 * @property int|null $take_id
 * @property int|null $credit_user_id
 * @property bool $is_default
 * @property Carbon|null $defaulted_at
 * @property string $media_key
 * @property int $sample_rate
 * @property int $frames
 * @property int $offset_frames
 * @property string $gain
 * @property int|null $kbps the bitrate it was encoded at; null for one made before 2026-11-13 (128)
 * @property Carbon $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TemplateVersion $templateVersion
 * @property-read BetaSubmission|null $submission
 * @property-read User|null $creditUser
 * @property-read AiVoice|null $aiVoice
 */
#[UseFactory(TemplateGuideFactory::class)]
#[ScopedBy(RecordedGuides::class)]
final class TemplateGuide extends Model
{
    /** @use HasFactory<TemplateGuideFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'part_key',
    ];

    protected function casts(): array
    {
        return [
            'source' => GuideSource::class,
            'is_default' => 'boolean',
            'defaulted_at' => 'datetime',
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'offset_frames' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /**
     * @return BelongsTo<BetaSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BetaSubmission::class, 'beta_submission_id');
    }

    /**
     * The singer of a guide from a song in the gallery, credited by their profile's name.
     *
     * @return BelongsTo<User, $this>
     */
    public function creditUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_user_id');
    }

    /**
     * @return BelongsTo<AiVoice, $this>
     */
    public function aiVoice(): BelongsTo
    {
        return $this->belongsTo(AiVoice::class);
    }

    /**
     * Guides of either source: AI ones too, for Admin, the worker and the rules that retire them.
     *
     * @return Builder<self>
     */
    public static function withAi(): Builder
    {
        return self::query()->withoutGlobalScope(RecordedGuides::class);
    }

    public function isAi(): bool
    {
        return $this->source === GuideSource::Ai;
    }

    /**
     * The name for "AI voice based on <name>'s singing, with permission", or null (a recorded guide, an AI voice not based
     * on a named person, or one whose singer chose no credit: AiVoice::labelName). Loads the voice if it isn't yet.
     */
    public function aiBasedOn(): ?string
    {
        if (! $this->isAi() || $this->ai_voice_id === null) {
            return null;
        }

        $this->loadMissing('aiVoice');

        return $this->aiVoice?->labelName();
    }

    /**
     * Admin's routes ({guide}) reach AI guides too: retiring one is removing it (admin.beta.guides.delete).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $id = $field === null && is_string($value) ? Ids::decode($value) : null;

        return $field !== null ? parent::resolveRouteBinding($value, $field) : ($id === null ? null : self::withAi()->whereKey($id)->first());
    }
}
