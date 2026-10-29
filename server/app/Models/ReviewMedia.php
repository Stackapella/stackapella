<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewMediaFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A coach's voice note or demonstration take for a review (F4), uploaded like a take, kept as AAC.
 *
 * @property int $id
 * @property string $uuid
 * @property int $review_item_id
 * @property int $user_id
 * @property string $kind
 * @property string $status
 * @property string|null $failure
 * @property int $sample_rate
 * @property int $frames
 * @property int $upload_bytes
 * @property string $sha256
 * @property string $upload_key
 * @property string|null $file_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ReviewItem $review
 * @property-read User $user
 */
#[UseFactory(ReviewMediaFactory::class)]
final class ReviewMedia extends Model
{
    /** @use HasFactory<ReviewMediaFactory> */
    use HasFactory;

    public const VOICE_NOTE = 'voice_note';

    public const DEMO = 'demo';

    public const UPLOADING = 'uploading';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $table = 'review_media';

    protected $fillable = [
        'kind',
        'sample_rate',
        'frames',
        'upload_bytes',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'upload_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ReviewItem, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ReviewItem::class, 'review_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
