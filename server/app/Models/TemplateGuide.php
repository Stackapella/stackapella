<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\TemplateGuideFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A real voice for one part of one template version, published on the CDN (an AAC in MP4 with its
 * edit list). The catalogue lists each part's default; the rest are alternates.
 *
 * @property int $id
 * @property int $template_version_id
 * @property string $part_key
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
 * @property Carbon $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TemplateVersion $templateVersion
 * @property-read BetaSubmission|null $submission
 * @property-read User|null $creditUser
 */
#[UseFactory(TemplateGuideFactory::class)]
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
}
