<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeagueFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A week's league of about 30 singers in one tier (N2).
 *
 * @property int $id
 * @property Carbon $week
 * @property int $tier
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(LeagueFactory::class)]
final class League extends Model
{
    /** @use HasFactory<LeagueFactory> */
    use HasFactory;

    public const SIZE = 30;

    public const PROMOTED = 5;

    public const TOP_TIER = 10;

    protected function casts(): array
    {
        return [
            'week' => 'date',
            'tier' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LeagueMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(LeagueMember::class);
    }
}
