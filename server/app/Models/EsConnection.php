<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\EsConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An account's connection to Event Schedule (G1, G5): by OAuth (the access and refresh tokens) or,
 * for a selfhosted install, an API key in `access_token`. Tokens and the webhook's secret are
 * encrypted and never leave the server. `schedule` is the one picked for lessons, whose webhook
 * reports booking changes to /webhooks/event-schedule/<sqid>.
 *
 * @property int $id
 * @property int $user_id
 * @property string $mode
 * @property string $base_url
 * @property string $es_user_id
 * @property string $es_user_name
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $expires_at
 * @property list<string>|null $scopes
 * @property string $status
 * @property string|null $schedule
 * @property string|null $schedule_name
 * @property string|null $schedule_timezone
 * @property string|null $webhook_id
 * @property string|null $webhook_secret
 * @property Carbon|null $webhook_failing_at
 * @property Carbon|null $failing_since
 * @property string|null $last_error
 * @property Carbon|null $disconnected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, LessonBooking> $lessons
 */
#[UseFactory(EsConnectionFactory::class)]
final class EsConnection extends Model
{
    /** @use HasFactory<EsConnectionFactory> */
    use HasFactory;

    use HasSqid;

    public const OAUTH = 'oauth';

    public const API_KEY = 'api_key';

    public const ACTIVE = 'active';

    public const NEEDS_RECONNECT = 'needs_reconnect';

    /** Disconnected, kept only while booked lessons are still to come (their webhook still works). */
    public const DISCONNECTED = 'disconnected';

    protected $hidden = ['access_token', 'refresh_token', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'webhook_failing_at' => 'datetime',
            'failing_since' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function usable(): bool
    {
        return $this->status === self::ACTIVE && $this->access_token !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LessonBooking, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(LessonBooking::class);
    }
}
