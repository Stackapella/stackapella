<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HarmonyLikeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A like on a harmony in the gallery (N1).
 *
 * @property int $harmony_id
 * @property int $user_id
 * @property Carbon|null $created_at
 */
#[UseFactory(HarmonyLikeFactory::class)]
final class HarmonyLike extends Model
{
    /** @use HasFactory<HarmonyLikeFactory> */
    use HasFactory;

    public $timestamps = false;

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
