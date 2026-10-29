<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LessonBookingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A live lesson booked in Event Schedule (G2): the engagement holds the money, this the booking as
 * ES knows it. `problem` marks one the reconcile couldn't match, or that never got booked.
 *
 * @property int $id
 * @property int $engagement_id
 * @property int|null $es_connection_id
 * @property string $appointment_type_id
 * @property string|null $es_booking_id
 * @property string|null $es_event_id
 * @property string $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $agreed_ends_at
 * @property string $timezone
 * @property string $guest_timezone
 * @property string|null $notes
 * @property int $cancellation_hours
 * @property Carbon $refund_until
 * @property string|null $manage_url
 * @property string|null $ical_url
 * @property string|null $location_url
 * @property string|null $changed_by
 * @property string|null $cancel_requested_by
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $synced_at
 * @property string|null $problem
 * @property string|null $problem_note
 * @property Carbon|null $problem_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Engagement $engagement
 * @property-read EsConnection|null $connection
 */
#[UseFactory(LessonBookingFactory::class)]
final class LessonBooking extends Model
{
    /** @use HasFactory<LessonBookingFactory> */
    use HasFactory;

    public const BOOKING = 'booking';

    public const PENDING_APPROVAL = 'pending_approval';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const MISMATCH = 'mismatch';

    public const BOOKING_FAILED = 'booking_failed';

    protected function casts(): array
    {
        return [
            'cancellation_hours' => 'integer',
            'refund_until' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'agreed_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
            'problem_at' => 'datetime',
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
     * @return BelongsTo<EsConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(EsConnection::class, 'es_connection_id');
    }
}
