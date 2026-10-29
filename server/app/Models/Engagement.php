<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\EngagementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A purchase from a coach, or a teacher link (F3): a review, a monthly package, a pack, a live
 * lesson (booked in Event Schedule, G2), or Send to my teacher; its money, its deadlines and its
 * reviews.
 *
 * @property int $id
 * @property string $kind
 * @property int|null $singer_id
 * @property int|null $coach_id
 * @property int|null $offering_id
 * @property int|null $pack_id
 * @property string $status
 * @property int $price_cents
 * @property string|null $currency
 * @property int $fee_cents
 * @property int $refunded_cents
 * @property int $period_refunded_cents
 * @property Carbon|null $disputed_at
 * @property int|null $turnaround_days
 * @property int|null $reviews_per_month
 * @property int|null $reviews_left
 * @property string|null $stripe_checkout_id
 * @property string|null $stripe_payment_intent
 * @property string|null $stripe_charge
 * @property string|null $stripe_transfer
 * @property string|null $stripe_subscription
 * @property Carbon|null $accept_by
 * @property Carbon|null $due_at
 * @property Carbon|null $period_ends_at
 * @property Carbon|null $ended_at
 * @property string|null $decline_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $singer
 * @property-read User|null $coach
 * @property-read CoachOffering|null $offering
 * @property-read ExercisePack|null $pack
 * @property-read Collection<int, ReviewItem> $reviews
 * @property-read CoachRating|null $rating
 * @property-read LessonBooking|null $lesson
 */
#[UseFactory(EngagementFactory::class)]
final class Engagement extends Model
{
    /** @use HasFactory<EngagementFactory> */
    use HasFactory;

    use HasSqid;

    public const REVIEW = 'review';

    public const PACKAGE = 'package';

    public const PACK = 'pack';

    public const TEACHER = 'teacher';

    public const LESSON = 'lesson';

    public const PENDING_PAYMENT = 'pending_payment';

    public const REQUESTED = 'requested';

    public const ACCEPTED = 'accepted';

    public const DELIVERED = 'delivered';

    public const DECLINED = 'declined';

    public const REFUNDED = 'refunded';

    public const CANCELLED = 'cancelled';

    public const ACTIVE = 'active';

    public const ENDING = 'ending';

    public const ENDED = 'ended';

    /** A lesson paid for, being booked in Event Schedule. */
    public const BOOKING = 'booking';

    /** A lesson booked (and confirmed) in Event Schedule. */
    public const BOOKED = 'booked';

    protected $fillable = [
        'kind',
        'price_cents',
        'currency',
        'turnaround_days',
        'reviews_per_month',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'fee_cents' => 'integer',
            'refunded_cents' => 'integer',
            'period_refunded_cents' => 'integer',
            'disputed_at' => 'datetime',
            'turnaround_days' => 'integer',
            'reviews_per_month' => 'integer',
            'reviews_left' => 'integer',
            'accept_by' => 'datetime',
            'due_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
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

    /**
     * @return BelongsTo<CoachOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(CoachOffering::class, 'offering_id');
    }

    /**
     * @return BelongsTo<ExercisePack, $this>
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(ExercisePack::class, 'pack_id');
    }

    /**
     * @return HasMany<ReviewItem, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    /**
     * @return HasMany<EngagementReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(EngagementReport::class);
    }

    /**
     * @return HasOne<CoachRating, $this>
     */
    public function rating(): HasOne
    {
        return $this->hasOne(CoachRating::class);
    }

    /**
     * @return HasOne<LessonBooking, $this>
     */
    public function lesson(): HasOne
    {
        return $this->hasOne(LessonBooking::class);
    }
}
