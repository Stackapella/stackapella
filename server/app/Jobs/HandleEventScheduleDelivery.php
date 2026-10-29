<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Coaching\Lessons;
use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\EsDelivery;
use App\Models\LessonBooking;
use App\Services\EventSchedule\HttpEventSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * One of Event Schedule's appointment webhooks, already checked and recorded by the controller:
 * applied to the lesson it's about, if it's one of ours on this coach's connection. A booking
 * made on ES's own page isn't ours, and is left alone.
 */
final class HandleEventScheduleDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly int $connectionId, public readonly array $payload) {}

    /**
     * Every try failed: the delivery is forgotten, so Event Schedule's redelivery is handled
     * rather than answered "already handled".
     */
    public function failed(?Throwable $e): void
    {
        $id = $this->payload['id'] ?? null;

        if (is_string($id) && $id !== '') {
            EsDelivery::query()->where('es_connection_id', $this->connectionId)->where('delivery_id', mb_substr($id, 0, 64))->delete();
        }
    }

    public function handle(Lessons $lessons): void
    {
        $data = (array) ($this->payload['data'] ?? []);
        $booking = HttpEventSchedule::booked((array) ($data['booking'] ?? []));
        $engagementId = $booking['externalId'] !== null ? Engagement::query()->whereSqid($booking['externalId'])->value('id') : null;

        // The booking's own id when we know it; our engagement's id (the booking's external_id)
        // only for a lesson not yet told its booking id, so a booking can't name another lesson.
        $lesson = $booking['id'] !== '' ? LessonBooking::query()->where('es_connection_id', $this->connectionId)->where('es_booking_id', $booking['id'])->first() : null;
        $lesson ??= $engagementId !== null ? LessonBooking::query()->where('es_connection_id', $this->connectionId)->where('engagement_id', $engagementId)->whereNull('es_booking_id')->first() : null;

        if ($lesson === null) {
            return;
        }

        $changedBy = isset($data['changed_by']) && is_string($data['changed_by']) ? $data['changed_by'] : null;
        $at = is_string($this->payload['created_at'] ?? null) ? Carbon::parse($this->payload['created_at']) : now();
        $lessons->apply($lesson, $booking, (string) ($this->payload['type'] ?? ''), $changedBy, $at->gt(now()) ? now() : $at);

        EsConnection::query()->whereKey($this->connectionId)->whereNotNull('webhook_failing_at')->update(['webhook_failing_at' => null]);
    }
}
