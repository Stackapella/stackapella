<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One post on our X or Bluesky account (docs/backend-plan.md D7, a port of iaw's): about a new
 * song, the song of the month, or a harmony its singer said may be featured. It waits for
 * Hillel's approval, then for the undo window; it's claimed before posting, so it goes once.
 *
 * @property int $id
 * @property string $postable_type
 * @property int $postable_id
 * @property string $occasion
 * @property string $network
 * @property string $status
 * @property string|null $remote_id
 * @property string|null $url
 * @property string|null $error
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $postable
 * @property-read User|null $approver
 */
#[UseFactory(SocialPostFactory::class)]
final class SocialPost extends Model
{
    /** @use HasFactory<SocialPostFactory> */
    use HasFactory;

    use HasSqid;

    public const NEW_SONG = 'new_song';

    public const MONTH = 'month';

    public const FEATURED = 'featured';

    public const AWAITING = 'awaiting';

    public const QUEUED = 'queued';

    public const POSTING = 'posting';

    public const POSTED = 'posted';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    /** Only ever written by SocialPoster. */
    protected $fillable = ['postable_type', 'postable_id', 'occasion', 'network', 'status'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function postable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
