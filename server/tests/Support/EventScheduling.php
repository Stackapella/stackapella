<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CoachOffering;
use App\Models\EsConnection;
use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\FakeEventSchedule;
use App\Services\EventSchedule\Signature;
use Illuminate\Testing\TestResponse;

/**
 * Event Schedule in the tests: the fake (contract/event-schedule.md), a coach connected to it with
 * a lesson on sale, and ES's webhooks signed as ES signs them.
 */
trait EventScheduling
{
    use Coaching;

    protected FakeEventSchedule $es;

    protected function fakeEventSchedule(): FakeEventSchedule
    {
        $this->es = new FakeEventSchedule;
        $this->app->instance(EventSchedule::class, $this->es);

        return $this->es;
    }

    protected function esConnection(User $user, string $schedule = 'grace-sings'): EsConnection
    {
        return EsConnection::factory()->withSchedule($schedule)->create(['user_id' => $user->id, 'es_user_name' => $user->name]);
    }

    /**
     * An approved coach connected to Event Schedule, with a lesson on sale (the fake's 45-minute
     * type, or its trial lesson that needs approval).
     *
     * @return array{0: User, 1: CoachOffering, 2: EsConnection}
     */
    protected function lessonCoach(string $type = 'Ap45'): array
    {
        [$coach] = $this->coach();
        $connection = $this->esConnection($coach);
        $offering = CoachOffering::factory()->create([
            'coach_id' => $coach->id,
            'kind' => CoachOffering::LESSON,
            'title' => 'A 45-minute lesson',
            'price_cents' => 4000,
            'turnaround_days' => 0,
            'max_seconds' => null,
            'es_appointment_type_id' => $type,
            'es_schedule' => 'grace-sings',
            'duration_minutes' => FakeEventSchedule::TYPES[$type]['durationMinutes'],
        ]);

        return [$coach, $offering, $connection];
    }

    /**
     * ES's webhook about a booking, as it sends it (signed `t=,v2=`).
     */
    protected function esWebhook(EsConnection $connection, string $type, string $bookingId, ?string $changedBy = null, ?int $at = null, ?string $deliveryId = null, ?string $secret = null): TestResponse
    {
        $booking = $this->es->state()['bookings'][$bookingId] ?? [];
        $body = (string) json_encode([
            'id' => $deliveryId ?? 'dl_'.uniqid(),
            'type' => $type,
            'created_at' => now()->utc()->toIso8601ZuluString(),
            'data' => [
                'booking' => [
                    'id' => $bookingId,
                    'event_id' => $booking['eventId'] ?? null,
                    'status' => $booking['status'] ?? 'confirmed',
                    'starts_at' => $booking['startsAt'] ?? null,
                    'ends_at' => $booking['endsAt'] ?? null,
                    'timezone' => 'Europe/London',
                    'manage_url' => $booking['manageUrl'] ?? null,
                    'ical_url' => $booking['icalUrl'] ?? null,
                    'location_url' => $booking['locationUrl'] ?? null,
                    'external_id' => $booking['externalId'] ?? null,
                ],
                'changed_by' => $changedBy,
                'reason' => null,
            ],
        ]);

        return $this->call('POST', '/webhooks/event-schedule/'.$connection->sqid(), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ES_SIGNATURE' => Signature::make($secret ?? (string) $connection->webhook_secret, $body, $at ?? now()->getTimestamp()),
        ], $body);
    }

    /**
     * The first free time the fake offers for a type, a few days out.
     */
    protected function freeSlot(EsConnection $connection, string $type = 'Ap45', int $daysAhead = 3): string
    {
        return $this->es->slots($connection, $type, now()->addDays($daysAhead)->toDateString(), 1)[0];
    }
}
