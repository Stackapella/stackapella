<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\AppDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One app sign-in (an iPhone, an Android phone, a desktop): what Settings lists under "Signed in
 * on". Only hashes of its tokens are kept; the app keeps the tokens in the OS keychain.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $platform
 * @property string|null $model
 * @property string|null $app_version
 * @property string $access_hash
 * @property Carbon $access_expires_at
 * @property string $refresh_hash
 * @property string|null $previous_refresh_hash
 * @property Carbon|null $refreshed_at
 * @property Carbon $refresh_expires_at
 * @property string $method
 * @property Carbon $signed_in_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property string|null $push_service apns or fcm
 * @property string|null $push_token
 * @property string|null $push_token_hash
 * @property string|null $push_environment sandbox or production (APNs)
 * @property Carbon|null $push_registered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(AppDeviceFactory::class)]
final class AppDevice extends Model
{
    /** @use HasFactory<AppDeviceFactory> */
    use HasFactory;

    use HasSqid;

    public const PLATFORMS = ['ios', 'android', 'macos', 'windows', 'linux'];

    /** Where a device's pushes go: Apple's (iOS, macOS) or Google's (Android). */
    public const PUSH_SERVICES = ['apns', 'fcm'];

    /** A device without a push token (signed out, or push turned off on it). */
    public const NO_PUSH = ['push_service' => null, 'push_token' => null, 'push_token_hash' => null, 'push_environment' => null, 'push_registered_at' => null];

    protected $hidden = ['push_token'];

    protected static function booted(): void
    {
        // Signed out: its push token goes with it, so nothing is sent to a device nobody's on.
        self::saving(static function (self $device): void {
            if ($device->revoked_at !== null && $device->push_token_hash !== null) {
                $device->forceFill(self::NO_PUSH);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'access_expires_at' => 'datetime',
            'refreshed_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'signed_in_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'push_token' => 'encrypted',
            'push_registered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
