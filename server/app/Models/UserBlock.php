<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserBlockFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A block (N1): the blocked person can't follow the blocker or comment on their harmonies, and the
 * blocker doesn't see their comments.
 *
 * @property int $blocker_id
 * @property int $blocked_id
 * @property Carbon|null $created_at
 */
#[UseFactory(UserBlockFactory::class)]
final class UserBlock extends Model
{
    /** @use HasFactory<UserBlockFactory> */
    use HasFactory;

    protected $fillable = ['blocker_id', 'blocked_id', 'created_at'];

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
