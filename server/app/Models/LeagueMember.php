<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeagueMemberFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One singer in a week's league (N2), with their capped XP for the week.
 *
 * @property int $id
 * @property int $league_id
 * @property int $user_id
 * @property int $xp
 * @property bool $promoted
 * @property Carbon|null $flagged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(LeagueMemberFactory::class)]
final class LeagueMember extends Model
{
    /** @use HasFactory<LeagueMemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'promoted' => 'boolean',
            'flagged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
