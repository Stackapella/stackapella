<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\CoachOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a coach sells (F2): a take review, a monthly package of reviews and messages, or a
 * live lesson (one of the coach's Event Schedule appointment types, G2).
 *
 * @property int $id
 * @property int $coach_id
 * @property string $kind
 * @property string $title
 * @property string $description
 * @property int $price_cents
 * @property string $currency
 * @property int $turnaround_days
 * @property int|null $max_seconds
 * @property int|null $reviews_per_month
 * @property Carbon|null $archived_at
 * @property string|null $es_appointment_type_id
 * @property string|null $es_schedule
 * @property int|null $duration_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $coach
 */
#[UseFactory(CoachOfferingFactory::class)]
final class CoachOffering extends Model
{
    /** @use HasFactory<CoachOfferingFactory> */
    use HasFactory;

    use HasSqid;

    public const REVIEW = 'review';

    public const PACKAGE = 'package';

    public const LESSON = 'lesson';

    protected $fillable = [
        'kind',
        'title',
        'description',
        'price_cents',
        'currency',
        'turnaround_days',
        'max_seconds',
        'reviews_per_month',
        'es_appointment_type_id',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'turnaround_days' => 'integer',
            'max_seconds' => 'integer',
            'reviews_per_month' => 'integer',
            'duration_minutes' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
