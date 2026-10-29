<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\LessonBooking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LessonBooking>
 */
final class LessonBookingFactory extends Factory
{
    protected $model = LessonBooking::class;

    public function definition(): array
    {
        $start = now()->addDays(3)->startOfHour();

        return [
            'engagement_id' => Engagement::factory(),
            'appointment_type_id' => 'Ap'.Str::random(4),
            'es_booking_id' => 'Bk'.Str::random(6),
            'status' => LessonBooking::CONFIRMED,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
            'timezone' => 'Europe/London',
            'guest_timezone' => 'Europe/London',
            'cancellation_hours' => 24,
            'refund_until' => $start->copy()->subDay(),
        ];
    }
}
