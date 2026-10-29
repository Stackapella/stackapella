<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\HarmonyConsentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A friend's answer to a harmony with their voice going in the gallery or being featured (N1).
 *
 * @property int $id
 * @property int $harmony_id
 * @property int $user_id
 * @property string $status
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(HarmonyConsentFactory::class)]
final class HarmonyConsent extends Model
{
    /** @use HasFactory<HarmonyConsentFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = ['harmony_id', 'user_id', 'status'];

    public const ASKED = 'asked';

    public const AGREED = 'agreed';

    public const DECLINED = 'declined';

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
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
