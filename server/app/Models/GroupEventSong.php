<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GroupEventSongFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of a choir group's songs picked for a rehearsal or concert in its Event Schedule (G3).
 *
 * @property int $id
 * @property int $group_id
 * @property string $es_event_id
 * @property int $group_song_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GroupSong $song
 */
#[UseFactory(GroupEventSongFactory::class)]
final class GroupEventSong extends Model
{
    /** @use HasFactory<GroupEventSongFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<GroupSong, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(GroupSong::class, 'group_song_id');
    }
}
