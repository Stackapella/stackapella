<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\BellItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing in someone's bell (N1, in the app only): a like (grouped per harmony a day), a comment, a
 * follow, a friend's consent asked or answered, a harmony featured, a friend beating your score.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property int|null $actor_id
 * @property int|null $harmony_id
 * @property int|null $idea_id
 * @property int|null $song_request_id
 * @property int|null $part_score_id
 * @property string|null $group_key
 * @property int $others
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(BellItemFactory::class)]
final class BellItem extends Model
{
    /** @use HasFactory<BellItemFactory> */
    use HasFactory;

    use HasSqid;

    public const KINDS = ['like', 'comment', 'follow', 'consent_request', 'consent_answer', 'featured'];

    protected function casts(): array
    {
        return [
            'others' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Harmony, $this>
     */
    public function harmony(): BelongsTo
    {
        return $this->belongsTo(Harmony::class);
    }

    /**
     * @return BelongsTo<Idea, $this>
     */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * @return BelongsTo<SongRequest, $this>
     */
    public function songRequest(): BelongsTo
    {
        return $this->belongsTo(SongRequest::class);
    }

    /**
     * The friend's score that beat yours (board_beaten).
     *
     * @return BelongsTo<PartScore, $this>
     */
    public function partScore(): BelongsTo
    {
        return $this->belongsTo(PartScore::class);
    }
}
