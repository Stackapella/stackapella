<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GroupAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Which part of a group's song a member sings.
 *
 * @property int $id
 * @property int $group_song_id
 * @property int $user_id
 * @property string $part_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GroupSong $song
 * @property-read User $user
 */
#[UseFactory(GroupAssignmentFactory::class)]
final class GroupAssignment extends Model
{
    /** @use HasFactory<GroupAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'part_key',
    ];

    /**
     * @return BelongsTo<GroupSong, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(GroupSong::class, 'group_song_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
