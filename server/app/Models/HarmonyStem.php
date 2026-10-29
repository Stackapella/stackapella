<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HarmonyStemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One part of a harmony with open parts (N5), copied from the take in its shared mix so a filler
 * sings with it, placed as the original's part was. Kept with the harmony, gone with it.
 *
 * @property int $id
 * @property int $harmony_id
 * @property int|null $user_id
 * @property string $part_key
 * @property string $file_key
 * @property int $stored_bytes
 * @property int $sample_rate
 * @property int $frames
 * @property int $offset_frames
 * @property int $trim_start_frames
 * @property int $trim_end_frames
 * @property string $volume
 * @property string $pan
 * @property int $frames_per_peak
 * @property list<float>|null $peaks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Harmony $harmony
 * @property-read User|null $user
 */
#[UseFactory(HarmonyStemFactory::class)]
final class HarmonyStem extends Model
{
    /** @use HasFactory<HarmonyStemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stored_bytes' => 'integer',
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'offset_frames' => 'integer',
            'trim_start_frames' => 'integer',
            'trim_end_frames' => 'integer',
            'frames_per_peak' => 'integer',
            'peaks' => 'array',
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
