<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\InvoiceNinjaConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Invoice Ninja company keeping someone's books (docs/invoice-ninja-integration-plan.md): a
 * coach's (`user:<id>`), or Stackapella's own (`platform`). The token and the webhook's secret
 * are encrypted and never leave the server.
 *
 * @property int $id
 * @property string $owner_key
 * @property int|null $user_id
 * @property string|null $api_url
 * @property string $token
 * @property string $company_id
 * @property string $company_name
 * @property bool $send_email
 * @property string $status
 * @property string|null $last_error
 * @property string|null $webhook_id
 * @property string|null $webhook_secret
 * @property Carbon|null $backfilled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[UseFactory(InvoiceNinjaConnectionFactory::class)]
final class InvoiceNinjaConnection extends Model
{
    /** @use HasFactory<InvoiceNinjaConnectionFactory> */
    use HasFactory;

    use HasSqid;

    public const PLATFORM = 'platform';

    public const ACTIVE = 'active';

    public const NEEDS_RECONNECT = 'needs_reconnect';

    protected $fillable = ['owner_key'];

    protected $hidden = ['token', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'send_email' => 'boolean',
            'backfilled_at' => 'datetime',
        ];
    }

    public static function ownerKey(?User $user): string
    {
        return $user !== null ? 'user:'.$user->id : self::PLATFORM;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
