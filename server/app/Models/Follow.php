<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person following another's public profile (N1).
 *
 * @property int $follower_id
 * @property int $followee_id
 * @property Carbon|null $created_at
 */
#[UseFactory(FollowFactory::class)]
final class Follow extends Model
{
    /** @use HasFactory<FollowFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function followee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followee_id');
    }
}
