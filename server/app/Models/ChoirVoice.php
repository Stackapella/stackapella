<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChoirVoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One singer's part in a choir (N6): a copy of their take, its level measured, credited by name
 * or anonymously. One per singer per choir; never shown on its own.
 *
 * @property int $id
 * @property string $uuid
 * @property int $choir_id
 * @property int $user_id
 * @property int|null $take_id
 * @property string $part_key
 * @property string $status
 * @property string $credit
 * @property int $score
 * @property string|null $file_key
 * @property int $sample_rate
 * @property int $frames
 * @property int $offset_frames
 * @property int $trim_start_frames
 * @property int $trim_end_frames
 * @property Carbon|null $held_at
 * @property string $gain_db
 * @property Carbon $rights_confirmed_at
 * @property Carbon|null $in_mix_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Choir $choir
 * @property-read User $user
 */
#[UseFactory(ChoirVoiceFactory::class)]
final class ChoirVoice extends Model
{
    /** @use HasFactory<ChoirVoiceFactory> */
    use HasFactory;

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /**
     * Whether a voice placed `$offset` seconds from the song's start, lasting `$seconds`, falls
     * within a song at all (no song is longer than a take may be): one that doesn't is left out of
     * the mix, so a far-off offset can't make ffmpeg write hours of silence (docs/security-review.md).
     */
    public static function inSong(float $offset, float $seconds): bool
    {
        return $offset < (int) config('usage.take_max_seconds') && $offset + $seconds > 0;
    }

    public const ANONYMOUS = 'anonymous';

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'offset_frames' => 'integer',
            'trim_start_frames' => 'integer',
            'trim_end_frames' => 'integer',
            'held_at' => 'datetime',
            'rights_confirmed_at' => 'datetime',
            'in_mix_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Choir, $this>
     */
    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
