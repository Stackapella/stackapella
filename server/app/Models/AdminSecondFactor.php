<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdminSecondFactorFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An admin's TOTP backup and recovery codes (App\Support\Admin\SecondFactor). Everything here is
 * server-owned, so nothing is fillable but the owner.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $totp_secret
 * @property Carbon|null $totp_confirmed_at
 * @property int|null $totp_last_timestep
 * @property list<string>|null $recovery_codes
 * @property int $failed_attempts
 * @property Carbon|null $last_failed_at
 * @property Carbon|null $locked_until
 */
#[UseFactory(AdminSecondFactorFactory::class)]
final class AdminSecondFactor extends Model
{
    /** @use HasFactory<AdminSecondFactorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
    ];

    protected $hidden = [
        'totp_secret',
        'recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'totp_last_timestep' => 'integer',
            'recovery_codes' => 'array',
            'failed_attempts' => 'integer',
            'last_failed_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasTotp(): bool
    {
        return $this->totp_secret !== null && $this->totp_confirmed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
