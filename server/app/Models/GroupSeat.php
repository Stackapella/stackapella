<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GroupSeatFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of a choir group's seats, held by a member (docs/choir-seats-plan.md): the leader buys them, they fill in join
 * order, and the leader moves one from a member to another. Server-owned: every column is set with forceFill().
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property Carbon $assigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group $group
 * @property-read User $user
 */
#[UseFactory(GroupSeatFactory::class)]
final class GroupSeat extends Model
{
    /** @use HasFactory<GroupSeatFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
