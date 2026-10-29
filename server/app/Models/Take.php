<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TakeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recording: immutable once made (a new take, never an edited one). Uploaded as a WAV, kept as
 * FLAC in `private` (docs/backend-plan.md C1, C4).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $part_id
 * @property int $project_id
 * @property string $status
 * @property string|null $failure
 * @property int $sample_rate
 * @property int $frames
 * @property int $offset_frames
 * @property int $upload_bytes
 * @property int|null $stored_bytes
 * @property string $sha256
 * @property int $frames_per_peak
 * @property list<float>|null $peaks
 * @property string $upload_key
 * @property string|null $file_key
 * @property int|null $replaces_take_id
 * @property Carbon|null $ready_at
 * @property int $seq
 * @property int|null $recorded_by
 * @property Carbon|null $binned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Part|null $part
 * @property-read Project $project
 * @property-read User $user
 * @property-read User|null $recorder
 */
#[UseFactory(TakeFactory::class)]
final class Take extends Model
{
    /** @use HasFactory<TakeFactory> */
    use HasFactory;

    public const UPLOADING = 'uploading';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /**
     * Every column but the peaks: a two-minute take's are about 60 KB, so a list that doesn't show
     * them (the bin, a sync page sizing itself) reads the rest alone.
     */
    public const WITHOUT_PEAKS = ['id', 'uuid', 'user_id', 'part_id', 'project_id', 'status', 'failure', 'sample_rate', 'frames', 'offset_frames', 'upload_bytes', 'stored_bytes', 'sha256', 'frames_per_peak', 'upload_key', 'file_key', 'replaces_take_id', 'ready_at', 'seq', 'binned_at', 'created_at', 'updated_at', 'recorded_by'];

    /** How long the bin keeps a take or a project. */
    public const BIN_DAYS = 30;

    protected $fillable = [
        'sample_rate',
        'frames',
        'offset_frames',
        'upload_bytes',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'offset_frames' => 'integer',
            'upload_bytes' => 'integer',
            'stored_bytes' => 'integer',
            'peaks' => 'array',
            'binned_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    /**
     * What the take counts toward storage: the FLAC once it's made, the WAV until then.
     */
    public function bytes(): int
    {
        return $this->stored_bytes ?? $this->upload_bytes;
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Who sang it, when that isn't the project's owner (a singer on a shared song).
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
