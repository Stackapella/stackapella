<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\SupportMessageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A "Contact us" or "Send diagnostics" message in the admin inbox (docs/operations-plan.md,
 * "Support"), with the app's context when the person chose to include it.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $category
 * @property string $body
 * @property array<string, mixed>|null $context
 * @property string $status
 * @property string|null $ip_hash
 * @property Carbon|null $answered_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, SupportReply> $replies
 */
#[UseFactory(SupportMessageFactory::class)]
final class SupportMessage extends Model
{
    /**
     * Kept two years after the last word on it (a reply, or a change of status), then deleted.
     */
    public const KEEP_MONTHS = 24;

    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory;

    use HasSqid;

    public const CATEGORIES = ['account', 'payments', 'recording', 'coaching', 'other', 'diagnostics', 'bug', 'idea'];

    public const OPEN = 'open';

    public const ANSWERED = 'answered';

    public const CLOSED = 'closed';

    protected $fillable = ['category', 'body', 'context', 'email'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'answered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SupportReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(SupportReply::class);
    }
}
