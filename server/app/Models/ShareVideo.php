<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\ShareVideoFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Share as a video (docs/more-features-plan.md, feature 4): a vertical MP4 of a section of a
 * harmony, made by RenderShareVideo and kept 7 days in `private` (`exports/videos/`).
 *
 * @property int $id
 * @property int $harmony_id
 * @property int $user_id
 * @property int $start_ms
 * @property int $end_ms
 * @property string $look
 * @property bool $words
 * @property string $status
 * @property string $progress
 * @property string|null $failure
 * @property string|null $file_key
 * @property int|null $bytes
 * @property Carbon|null $started_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Harmony $harmony
 * @property-read User $user
 */
#[UseFactory(ShareVideoFactory::class)]
final class ShareVideo extends Model
{
    /** @use HasFactory<ShareVideoFactory> */
    use HasFactory;

    use HasSqid;

    public const QUEUED = 'queued';

    public const RENDERING = 'rendering';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'start_ms',
        'end_ms',
        'look',
        'words',
    ];

    protected function casts(): array
    {
        return [
            'start_ms' => 'integer',
            'end_ms' => 'integer',
            'words' => 'boolean',
            'bytes' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Harmony, $this>
     */
    public function harmony(): BelongsTo
    {
        return $this->belongsTo(Harmony::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
