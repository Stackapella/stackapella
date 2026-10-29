<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IdeaVoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One singer's part on an idea: uploaded as a take is, kept as AAC in the private bucket, heard by
 * temporary URL. Each singer keeps one per part.
 *
 * @property int $id
 * @property string $uuid
 * @property int $idea_id
 * @property int $user_id
 * @property string $part_key
 * @property string $status
 * @property string|null $failure
 * @property int $sample_rate
 * @property int $frames
 * @property int $offset_frames
 * @property int $upload_bytes
 * @property string $sha256
 * @property string $upload_key
 * @property string|null $file_key
 * @property int|null $stored_bytes
 * @property int|null $frames_per_peak
 * @property list<float>|null $peaks
 * @property Carbon $rights_confirmed_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Idea $idea
 * @property-read User $user
 */
#[UseFactory(IdeaVoiceFactory::class)]
final class IdeaVoice extends Model
{
    /** @use HasFactory<IdeaVoiceFactory> */
    use HasFactory;

    public const UPLOADING = 'uploading';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'offset_frames' => 'integer',
            'upload_bytes' => 'integer',
            'stored_bytes' => 'integer',
            'frames_per_peak' => 'integer',
            'peaks' => 'array',
            'rights_confirmed_at' => 'datetime',
            'ready_at' => 'datetime',
            'hidden_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Idea, $this>
     */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
