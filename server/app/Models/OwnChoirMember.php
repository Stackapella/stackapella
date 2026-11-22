<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use App\Support\Ids;
use Database\Factories\OwnChoirMemberFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A friend in another singer's choir song (docs/choir-together-plan.md): the parts their voice sings in it, their yes
 * (the own-choir terms' version, an own song's words hash), whether their name shows on shared pages.
 *
 * @property int $id
 * @property int $own_choir_request_id the owner's song
 * @property int|null $user_id the friend (null once their account is gone)
 * @property list<string> $parts
 * @property int $consent_version
 * @property string|null $words_sha
 * @property bool $show_name
 * @property Carbon $joined_at
 * @property Carbon|null $seen_at their parts seen ready
 * @property string $name their first name, kept for the owner's notice after they leave
 * @property Carbon|null $left_at
 * @property string|null $left_reason left, removed, voice_deleted or song_deleted
 * @property Carbon|null $owner_seen_at the owner saw their leaving
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OwnChoirRequest $song
 * @property-read User|null $user
 * @property-read OwnChoirRequest|null $job the render of their parts
 */
#[UseFactory(OwnChoirMemberFactory::class)]
final class OwnChoirMember extends Model
{
    /** @use HasFactory<OwnChoirMemberFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['parts' => 'array', 'consent_version' => 'integer', 'show_name' => 'boolean', 'joined_at' => 'datetime', 'seen_at' => 'datetime', 'left_at' => 'datetime', 'owner_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<OwnChoirRequest, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(OwnChoirRequest::class, 'own_choir_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<OwnChoirRequest, $this>
     */
    public function job(): HasOne
    {
        return $this->hasOne(OwnChoirRequest::class, 'own_choir_member_id')->latestOfMany();
    }

    public function sqid(): string
    {
        return Ids::encode($this->id);
    }
}
