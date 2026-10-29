<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use App\Support\Trust\IpHash;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only record of every admin, moderation and money action. Rows are never changed or
 * deleted: the model refuses both.
 *
 * @property int $id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $actor_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property string|null $ip_hash
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read Model|null $subject
 */
#[UseFactory(AuditLogFactory::class)]
final class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    use HasSqid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'action',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Audit log rows are never changed.'));
        self::deleting(static fn (): never => throw new LogicException('Audit log rows are never deleted.'));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(string $action, ?Model $subject, ?User $actor, ?array $before = null, ?array $after = null, ?string $reason = null, ?string $ip = null): self
    {
        $log = new self;
        $log->forceFill([
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'actor_id' => $actor?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_hash' => $ip !== null ? IpHash::of($ip) : null,
        ])->save();

        return $log;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
