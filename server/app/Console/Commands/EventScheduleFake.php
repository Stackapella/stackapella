<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Coaching\Lessons;
use App\Jobs\HandleEventScheduleDelivery;
use App\Models\Engagement;
use App\Models\LessonBooking;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\FakeEventSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Locally, with the fake Event Schedule on: play ES's side of a lesson, as its coach or singer
 * would in ES, and send the webhook ES would (handled at once, as the queue would). For trying the
 * app's lesson screens; never in production.
 */
final class EventScheduleFake extends Command
{
    protected $signature = 'app:event-schedule:fake {engagement : the lesson engagement\'s id} {change : cancelled, completed, rescheduled or confirmed} {--by=owner : owner (the coach) or guest (the singer)}';

    protected $description = 'Change a lesson in the fake Event Schedule and deliver its webhook (local only)';

    public function handle(EventSchedule $es): int
    {
        if (! $es instanceof FakeEventSchedule || app()->isProduction()) {
            $this->error('This works only with EVENT_SCHEDULE_FAKE=true, and never in production.');

            return self::FAILURE;
        }

        $change = (string) $this->argument('change');
        $engagement = Engagement::query()->whereSqid((string) $this->argument('engagement'))->first();
        $lesson = $engagement !== null ? LessonBooking::query()->where('engagement_id', $engagement->id)->first() : null;

        if ($lesson === null || $lesson->es_booking_id === null || $lesson->es_connection_id === null || ! in_array($change, ['cancelled', 'completed', 'rescheduled', 'confirmed'], true)) {
            $this->error('No booked lesson by that id, or not a change the fake makes.');

            return self::FAILURE;
        }

        $booking = $es->set($lesson->es_booking_id, match ($change) {
            'rescheduled' => ['startsAt' => $lesson->starts_at->copy()->addDay()->utc()->toIso8601ZuluString(), 'endsAt' => $lesson->ends_at->copy()->addDay()->utc()->toIso8601ZuluString()],
            default => ['status' => $change],
        });

        (new HandleEventScheduleDelivery($lesson->es_connection_id, [
            'id' => 'dl_'.Str::random(12),
            'type' => 'appointment.'.$change,
            'created_at' => Carbon::now()->utc()->toIso8601ZuluString(),
            'data' => ['booking' => [
                'id' => $booking['id'], 'event_id' => $booking['eventId'], 'status' => $booking['status'], 'starts_at' => $booking['startsAt'], 'ends_at' => $booking['endsAt'],
                'timezone' => $booking['timezone'], 'manage_url' => $booking['manageUrl'], 'ical_url' => $booking['icalUrl'], 'location_url' => $booking['locationUrl'], 'external_id' => $booking['externalId'],
            ], 'changed_by' => (string) $this->option('by'), 'reason' => null],
        ]))->handle(app(Lessons::class));

        $this->info("Lesson {$engagement?->sqid()}: {$change} by the ".((string) $this->option('by') === 'guest' ? 'singer' : 'coach').'.');

        return self::SUCCESS;
    }
}
