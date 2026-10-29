<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\CoachMessageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message in an engagement's thread (F5): plain text or a voice note.
 *
 * @property int $id
 * @property int $engagement_id
 * @property int $sender_id
 * @property string|null $body
 * @property int|null $media_id
 * @property string|null $warning
 * @property-read Engagement $engagement
 * @property-read User $sender
 * @property-read ReviewMedia|null $media
 */
#[UseFactory(CoachMessageFactory::class)]
final class CoachMessage extends Model
{
    /** @use HasFactory<CoachMessageFactory> */
    use HasFactory;

    use HasSqid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'body',
        'warning',
    ];

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<ReviewMedia, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(ReviewMedia::class, 'media_id');
    }
}
