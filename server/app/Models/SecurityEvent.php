<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecurityEventType;
use App\Models\Concerns\HasSqid;
use Database\Factories\SecurityEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing worth knowing when investigating abuse. Written by App\Support\Trust\SecurityLog; the
 * raw IP is cleared after 30 days by app:prune-security-events, leaving the HMAC.
 *
 * @property int $id
 * @property SecurityEventType $type
 * @property int|null $user_id
 * @property string|null $ip
 * @property string|null $ip_hash
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[UseFactory(SecurityEventFactory::class)]
final class SecurityEvent extends Model
{
    /** @use HasFactory<SecurityEventFactory> */
    use HasFactory;

    use HasSqid;

    public const UPDATED_AT = null;

    /** Days a raw IP is kept, after which only its HMAC remains. */
    public const RAW_IP_DAYS = 30;

    /** Days a row is kept at all. */
    public const KEEP_DAYS = 365;

    protected $fillable = [
        'type',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'type' => SecurityEventType::class,
            'context' => 'array',
            'created_at' => 'datetime',
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
