<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser this person has signed in from: an HMAC of a random cookie we set, never a fingerprint.
 *
 * @property int $id
 * @property int $user_id
 * @property string $device_hash
 * @property Carbon $last_seen_at
 */
#[UseFactory(UserDeviceFactory::class)]
final class UserDevice extends Model
{
    /** @use HasFactory<UserDeviceFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'device_hash',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
