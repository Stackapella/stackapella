<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use App\Support\Ids;
use Database\Factories\OwnChoirInviteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invite to sing in a choir song: used once, for own_choir.together.invite_days, revocable; only its token's hash.
 *
 * @property int $id
 * @property int $own_choir_request_id
 * @property string $token_hash
 * @property list<string> $parts the parts the owner hopes a friend sings
 * @property Carbon $expires_at
 * @property int|null $used_by_member_id
 * @property Carbon|null $used_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OwnChoirRequest $song
 */
#[UseFactory(OwnChoirInviteFactory::class)]
final class OwnChoirInvite extends Model
{
    /** @use HasFactory<OwnChoirInviteFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['parts' => 'array', 'expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OwnChoirRequest, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(OwnChoirRequest::class, 'own_choir_request_id');
    }

    /** @return 'open'|'used'|'expired'|'revoked' */
    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->used_at !== null => 'used',
            $this->expires_at->isPast() => 'expired',
            default => 'open',
        };
    }

    public function sqid(): string
    {
        return Ids::encode($this->id);
    }
}
