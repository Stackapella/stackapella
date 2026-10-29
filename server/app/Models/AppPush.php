<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AppPushFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A push to the person's apps for one new bell item (AppPushes): held until `send_after` (the end
 * of their quiet hours), then sent to each of their devices with a push token, or dropped if it
 * couldn't go within 12 hours. Its words are made when it's sent, from the bell item.
 *
 * @property int $id
 * @property int $user_id
 * @property int $bell_item_id
 * @property Carbon $send_after
 * @property Carbon|null $sent_at
 * @property Carbon|null $dropped_at
 * @property string|null $outcome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read BellItem $bellItem
 */
#[UseFactory(AppPushFactory::class)]
final class AppPush extends Model
{
    /** @use HasFactory<AppPushFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'send_after' => 'datetime',
            'sent_at' => 'datetime',
            'dropped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BellItem, $this>
     */
    public function bellItem(): BelongsTo
    {
        return $this->belongsTo(BellItem::class);
    }
}
