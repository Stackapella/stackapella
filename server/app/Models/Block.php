<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockType;
use App\Models\Concerns\HasSqid;
use Database\Factories\BlockFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry on the blocklist (App\Support\Trust\Blocklist).
 *
 * @property int $id
 * @property BlockType $type
 * @property string $value
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
#[UseFactory(BlockFactory::class)]
final class Block extends Model
{
    /** @use HasFactory<BlockFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'type',
        'value',
        'reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
