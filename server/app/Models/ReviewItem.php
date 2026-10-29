<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\ReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One take sent for review, and the coach's review of it (F4): the server's own copy of the take,
 * its pitch track and scores, the question, the draft and what was delivered, and the follow-up.
 *
 * @property int $id
 * @property int $engagement_id
 * @property string $status
 * @property string $take_key
 * @property int $sample_rate
 * @property int $frames
 * @property string|null $template_slug
 * @property int|null $template_version
 * @property string|null $part_key
 * @property string|null $pitch_track_key
 * @property array<string, mixed>|null $scores
 * @property string $question
 * @property array<string, mixed>|null $draft
 * @property array<string, mixed>|null $delivered
 * @property Carbon|null $due_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $released_at
 * @property string|null $follow_up_question
 * @property Carbon|null $follow_up_at
 * @property string|null $follow_up_answer
 * @property string|null $follow_up_media
 * @property Carbon|null $follow_up_answered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Engagement $engagement
 * @property-read Collection<int, ReviewMedia> $media
 */
#[UseFactory(ReviewItemFactory::class)]
final class ReviewItem extends Model
{
    /** @use HasFactory<ReviewItemFactory> */
    use HasFactory;

    use HasSqid;

    public const REQUESTED = 'requested';

    public const ACCEPTED = 'accepted';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'question',
        'template_slug',
        'template_version',
        'part_key',
        'scores',
    ];

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'template_version' => 'integer',
            'scores' => 'array',
            'draft' => 'array',
            'delivered' => 'array',
            'due_at' => 'datetime',
            'delivered_at' => 'datetime',
            'released_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'follow_up_answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * @return HasMany<ReviewMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class);
    }
}
