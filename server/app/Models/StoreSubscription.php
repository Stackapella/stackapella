<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Counts\RecordCounts;
use Database\Factories\StoreSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Plus bought in an app (App\Actions\Billing\StoreSubscriptions): an App Store or Google Play
 * subscription, tied to the account that bought it, kept up to date by the stores' notifications.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $store
 * @property string $original_id
 * @property string|null $purchase_token
 * @property string|null $token_hash
 * @property string $product_id
 * @property string|null $interval
 * @property string $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $grace_until
 * @property bool $will_renew
 * @property string $environment
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $last_event_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[UseFactory(StoreSubscriptionFactory::class)]
final class StoreSubscription extends Model
{
    /** @use HasFactory<StoreSubscriptionFactory> */
    use HasFactory;

    public const APPLE = 'apple';

    public const GOOGLE = 'google';

    public const STORES = [self::APPLE, self::GOOGLE];

    /** Paid and current. */
    public const ACTIVE = 'active';

    /** A renewal failed, and the store keeps it going while it retries (Plus stays). */
    public const GRACE = 'grace';

    /** A renewal failed, past any grace: the store still retries, but Plus is off. */
    public const RETRYING = 'retrying';

    public const EXPIRED = 'expired';

    /** Refunded, or taken back (Apple's revoke; Google's voided purchase). */
    public const REVOKED = 'revoked';

    /** Only which subscription it is, when a row is made; the rest is the server's own. */
    protected $fillable = ['store', 'original_id'];

    /**
     * Growth's "Plus started" (a daily number only): a real store subscription that starts running,
     * new or after it had ended. A renewal out of grace isn't a start, nor a store's test purchase.
     */
    protected static function booted(): void
    {
        self::created(static function (self $subscription): void {
            if ($subscription->status === self::ACTIVE) {
                $subscription->countStart();
            }
        });
        self::updated(static function (self $subscription): void {
            if ($subscription->wasChanged('status') && $subscription->status === self::ACTIVE
                && ! in_array($subscription->getPrevious()['status'] ?? null, [self::ACTIVE, self::GRACE, self::RETRYING], true)) {
                $subscription->countStart();
            }
        });
    }

    private function countStart(): void
    {
        if ($this->environment === 'Production') {
            app(RecordCounts::class)->server('plus_started', true, null);
        }
    }

    protected $hidden = ['purchase_token'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The day this gives Plus until (null: none now). A sandbox purchase (TestFlight, App Review,
     * Google's test purchases) gives Plus only to an account marked as a store tester.
     */
    public function plusUntil(): ?Carbon
    {
        if ($this->environment === 'Sandbox' && $this->user?->store_tester_at === null) {
            return null;
        }

        return match ($this->status) {
            self::ACTIVE => $this->expires_at,
            self::GRACE => $this->grace_until ?? $this->expires_at,
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'purchase_token' => 'encrypted',
            'expires_at' => 'datetime',
            'grace_until' => 'datetime',
            'will_renew' => 'boolean',
            'acknowledged_at' => 'datetime',
            'refunded_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }
}
