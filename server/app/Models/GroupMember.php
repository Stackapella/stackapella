<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GroupMemberFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone in a choir group: its director (role director) or a member.
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group $group
 * @property-read User $user
 */
#[UseFactory(GroupMemberFactory::class)]
final class GroupMember extends Model
{
    /** @use HasFactory<GroupMemberFactory> */
    use HasFactory;

    public const DIRECTOR = 'director';

    public const MEMBER = 'member';

    protected $fillable = [
        'role',
    ];

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
