<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\CoachRatingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A singer's rating of a finished engagement (F6), with the coach's one reply.
 *
 * @property int $id
 * @property int $engagement_id
 * @property int|null $singer_id
 * @property int $coach_id
 * @property int $stars
 * @property string|null $text
 * @property string|null $reply
 * @property Carbon|null $replied_at
 * @property Carbon|null $removed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Engagement $engagement
 * @property-read User $singer
 * @property-read User $coach
 */
#[UseFactory(CoachRatingFactory::class)]
final class CoachRating extends Model
{
    /** @use HasFactory<CoachRatingFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'stars',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'replied_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function singer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'singer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
