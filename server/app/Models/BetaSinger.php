<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditMode;
use App\Models\Concerns\HasSqid;
use App\Support\Gallery\Gallery;
use Database\Factories\BetaSingerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A singer invited to the beta (docs/beta-singers-plan.md, and Hillel's decision "beta singers have
 * accounts"): the invite link attaches this record to their own account, once. Their credit is
 * that account's name, read when it's shown, unless they chose to stay anonymous.
 *
 * @property int $id
 * @property int|null $user_id
 * @property Carbon|null $accepted_at
 * @property string $name
 * @property string|null $email
 * @property string|null $note
 * @property string $token_hash
 * @property Carbon|null $forgotten_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $consent_version
 * @property Carbon|null $consented_at
 * @property CreditMode|null $credit_mode
 * @property bool $credit_links_profile
 * @property int|null $hourly_rate_cents
 * @property string|null $currency
 * @property Carbon|null $last_seen_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[UseFactory(BetaSingerFactory::class)]
final class BetaSinger extends Model
{
    /** @use HasFactory<BetaSingerFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'name',
        'email',
        'note',
        'expires_at',
        'hourly_rate_cents',
        'currency',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'forgotten_at' => 'datetime',
            'accepted_at' => 'datetime',
            'consented_at' => 'datetime',
            'consent_version' => 'integer',
            'credit_mode' => CreditMode::class,
            'credit_links_profile' => 'boolean',
            'hourly_rate_cents' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<BetaAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(BetaAssignment::class);
    }

    public function hasConsented(int $version): bool
    {
        return $this->consented_at !== null && $this->consent_version === $version;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The credit learners see: the account's name as it is now, or null for anonymous (and
     * before consent, or once the account is gone). Needs `user` loaded.
     */
    public function credit(): ?string
    {
        $name = $this->user !== null ? trim($this->user->name) : '';

        return $this->credit_mode === CreditMode::Name && $name !== '' ? $name : null;
    }

    /**
     * Their profile's handle, for the credit to link to: only when credited by name, they chose to
     * link it, their profile is public (an adult who chose to), and profiles are open (the
     * gallery). Needs `user` loaded.
     */
    public function creditHandle(): ?string
    {
        return $this->credit() !== null && (bool) ($this->getAttributes()['credit_links_profile'] ?? false) && $this->user !== null
            && Gallery::hasPublicProfile($this->user) && app(Gallery::class)->isOpen() ? $this->user->handle : null;
    }

    /**
     * Still a singer: not revoked, not forgotten.
     */
    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->forgotten_at === null;
    }

    /**
     * The invite can still be accepted.
     */
    public function inviteOpen(): bool
    {
        return $this->user_id === null && $this->isLive() && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
