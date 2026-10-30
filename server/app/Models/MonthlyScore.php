<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MonthlyScoreFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A singer's best counted score on a part this calendar month (UTC, "YYYY-MM"), for game mode's
 * monthly board. Kept for this month and last; older months are pruned.
 *
 * @property int $id
 * @property string $month
 * @property int $user_id
 * @property int $template_version_id
 * @property string $part_key
 * @property int $scoring_version
 * @property int $score
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(MonthlyScoreFactory::class)]
final class MonthlyScore extends Model
{
    /** @use HasFactory<MonthlyScoreFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function thisMonth(): string
    {
        return now()->utc()->format('Y-m');
    }
}
