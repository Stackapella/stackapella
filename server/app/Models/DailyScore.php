<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DailyScoreFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A singer's best on one day's challenge (App\Support\Boards\Daily): one a day, kept 30 days.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $user_id
 * @property int $template_id
 * @property int $template_version_id
 * @property string $part_key
 * @property int $scoring_version
 * @property int $score
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[UseFactory(DailyScoreFactory::class)]
final class DailyScore extends Model
{
    /** @use HasFactory<DailyScoreFactory> */
    use HasFactory;

    /** Days a score is kept (the boards show the last seven). */
    public const KEEP_DAYS = 30;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recorded_at' => 'datetime',
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
