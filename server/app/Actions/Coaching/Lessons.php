<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Exceptions\Refusal;
use App\Jobs\BookLesson;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\LessonBooking;
use App\Models\User;
use App\Services\Coaching\CoachingGateway;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\EventScheduleError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Live lessons, booked in Event Schedule (docs/event-schedule-integration-plan.md G2). The singer
 * pays first; a queued job (BookLesson) then books the time in ES as already paid. What ES says
 * afterwards (by webhook, or the hourly reconcile) is applied here in one place, `apply()`:
 * - a cancellation refunds under the coach's policy: by the coach, in full; by the singer before
 *   `refund_until` (set when it was bought), in full; later, nothing, and the coach's share moves.
 *   Only ES's hosted service is believed about who cancelled: a selfhosted install is the coach's
 *   own, so its cancellations always refund in full;
 * - a lesson counts as done once it has ended and ES says so (or a day after its end with nothing
 *   cancelled), never before it ends: then the coach's share moves;
 * - a reschedule moves the times; only the coach's can move `refund_until` later.
 *
 * Every refund and transfer carries an idempotency key made from what it does. A transfer that
 * fails never undoes what happened: the deadlines try it again.
 *
 * @phpstan-import-type Booking from EventSchedule
 */
final class Lessons
{
    private const OPEN = [LessonBooking::BOOKING, LessonBooking::PENDING_APPROVAL, LessonBooking::CONFIRMED];

    public function __construct(private readonly EventSchedule $es, private readonly CoachingGateway $stripe) {}

    /**
     * The coach's working connection when this lesson is on sale (its type is in the schedule
     * the connection has picked now), else null.
     */
    public static function connectionFor(CoachOffering $offering): ?EsConnection
    {
        if ($offering->kind !== CoachOffering::LESSON || $offering->es_appointment_type_id === null || $offering->archived_at !== null) {
            return null;
        }

        $connection = EsConnection::query()->where('user_id', $offering->coach_id)->first();

        return $connection !== null && $connection->usable() && $connection->schedule !== null && $connection->schedule === $offering->es_schedule ? $connection : null;
    }

    /**
     * A coach's lesson by their handle and its id, for coaches.slots.
     */
    public static function offering(string $handle, string $sqid): CoachOffering
    {
        return CoachOffering::query()->whereSqid($sqid)->where('kind', CoachOffering::LESSON)->whereHas('coach', fn ($query) => $query->where('handle', $handle)->whereNull('suspended_at'))->first()
            ?? throw new Refusal(404, 'not_found');
    }

    /**
     * The next free times, at most 30 seconds old, labelled in the singer's time zone.
     *
     * @return array{timezone: string, durationMinutes: int, slots: list<array{startsAt: string, date: string, label: string}>}
     */
    public function slots(CoachOffering $offering, ?string $from, int $days, string $timezone): array
    {
        $connection = self::connectionFor($offering);

        if ($connection === null || ! Engagements::coachAvailable($offering->coach_id, needsCharges: true)) {
            throw new Refusal(404, 'not_found');
        }

        $from ??= now($timezone)->toDateString();
        $starts = Cache::remember("es-slots:{$offering->id}:{$from}:{$days}", 30, fn (): array => $this->es->slots($connection, (string) $offering->es_appointment_type_id, $from, $days));

        return [
            'timezone' => $timezone,
            'durationMinutes' => (int) $offering->duration_minutes,
            'slots' => array_values(array_map(static function (string $start) use ($timezone): array {
                $local = Carbon::parse($start)->setTimezone($timezone);

                return ['startsAt' => Carbon::parse($start)->utc()->toIso8601ZuluString(), 'date' => $local->toDateString(), 'label' => $local->format('g:i A')];
            }, $starts)),
        ];
    }

    /**
     * Before Checkout: the time is asked of ES afresh (`409 slot_taken` when it's gone).
     *
     * @return array{connection: EsConnection, startsAt: Carbon}
     */
    public function check(CoachOffering $offering, string $slot): array
    {
        $connection = self::connectionFor($offering) ?? throw new Refusal(409, 'coach_not_ready');
        $start = Carbon::parse($slot)->utc();
        $free = $this->es->slots($connection, (string) $offering->es_appointment_type_id, $start->copy()->setTimezone((string) ($connection->schedule_timezone ?? 'UTC'))->subDay()->toDateString(), 3);

        if (! in_array($start->toIso8601ZuluString(), array_map(static fn (string $s): string => Carbon::parse($s)->utc()->toIso8601ZuluString(), $free), true)) {
            throw new EventScheduleError(EventScheduleError::SLOT_TAKEN);
        }

        return ['connection' => $connection, 'startsAt' => $start];
    }

    /**
     * Paid: book it in ES (the job's work). ES being down or the coach's connection broken throws,
     * so the job tries again; a time that went meanwhile is refunded at once.
     */
    public function book(Engagement $engagement): void
    {
        $lesson = LessonBooking::query()->with('connection')->where('engagement_id', $engagement->id)->first();

        if ($lesson === null || $engagement->fresh()?->status !== Engagement::BOOKING || $lesson->status !== LessonBooking::BOOKING) {
            return;
        }

        $connection = $lesson->connection;
        $singer = $engagement->singer;

        if ($connection === null || ! $connection->usable() || $singer === null) {
            throw new EventScheduleError(EventScheduleError::RECONNECT, "A paid lesson can't be booked yet: {$engagement->sqid()}.");
        }

        try {
            $booking = $this->es->book($connection, $lesson->appointment_type_id, [
                'externalId' => $engagement->sqid(),
                'slot' => $lesson->starts_at->utc()->toIso8601ZuluString(),
                'name' => $singer->name,
                'email' => (string) $singer->email,
                'guestTimezone' => $lesson->guest_timezone,
                'notes' => $lesson->notes,
            ], "lesson-book-{$engagement->id}");
        } catch (EventScheduleError $e) {
            if ($e->reason !== EventScheduleError::SLOT_TAKEN) {
                throw $e;
            }

            $this->fail($lesson, 'slot_taken');

            return;
        }

        $this->booked($lesson, $booking);
    }

    /**
     * The job gave up: if ES has the booking after all (its answer was lost), it's booked;
     * otherwise the singer gets their money back and Hillel sees it.
     */
    public function bookingFailed(Engagement $engagement, ?Throwable $why): void
    {
        $lesson = LessonBooking::query()->with('connection')->where('engagement_id', $engagement->id)->first();

        if ($lesson === null || $lesson->status !== LessonBooking::BOOKING) {
            return;
        }

        if ($lesson->connection !== null && $lesson->connection->usable()) {
            try {
                $found = $this->es->bookingByExternalId($lesson->connection, $engagement->sqid());

                if ($found !== null && $found['status'] !== 'cancelled') {
                    $this->booked($lesson, $found);

                    return;
                }
            } catch (EventScheduleError $e) {
                report($e);
            }
        }

        $this->fail($lesson, 'booking_failed', LessonBooking::BOOKING_FAILED, $why !== null ? mb_substr($why->getMessage(), 0, 190) : null);
    }

    /**
     * What ES says about a lesson (a webhook, or the reconcile). `$changedBy` is ES's: `owner`
     * (the coach), `guest` (the singer), `app` (us: the cancellation we asked for, as recorded
     * before we asked), or null when unknown.
     *
     * @param  Booking  $booking
     */
    public function apply(LessonBooking $lesson, array $booking, string $event, ?string $changedBy, Carbon $at): void
    {
        $lesson->loadMissing('connection');

        if (in_array($lesson->status, [LessonBooking::CANCELLED, LessonBooking::COMPLETED, LessonBooking::FAILED], true)) {
            return;
        }

        $hosted = $lesson->connection === null || $lesson->connection->mode === EsConnection::OAUTH;

        if ($event === 'appointment.cancelled' || $booking['status'] === 'cancelled') {
            $by = match (true) {
                $changedBy === 'app' && $lesson->cancel_requested_by !== null => $lesson->cancel_requested_by,
                $changedBy === 'guest' && $hosted => 'singer',
                default => 'coach',
            };
            $this->settleCancel($lesson, $by, $at, null);

            return;
        }

        if ($lesson->status === LessonBooking::BOOKING) {
            $this->booked($lesson, $booking);
        }

        DB::transaction(function () use ($lesson, $booking, $event, $changedBy): void {
            $locked = LessonBooking::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LessonBooking::PENDING_APPROVAL, LessonBooking::CONFIRMED], true)) {
                return;
            }

            $was = $locked->starts_at->copy();
            $starts = $booking['startsAt'] !== '' ? Carbon::parse($booking['startsAt']) : $was;
            $locked->forceFill(array_filter([
                'starts_at' => $starts,
                'ends_at' => $booking['endsAt'] !== '' ? Carbon::parse($booking['endsAt']) : null,
                'timezone' => $booking['timezone'] !== '' ? $booking['timezone'] : null,
                'manage_url' => self::link($booking['manageUrl']),
                'ical_url' => self::link($booking['icalUrl']),
                'location_url' => self::link($booking['locationUrl']),
                'es_event_id' => $booking['eventId'],
            ], static fn (mixed $value): bool => $value !== null));

            // A move: the coach's moves the policy's deadline with it (with less notice than the
            // policy, the singer may cancel in full until it starts); the singer's can't push it later.
            if (! $starts->equalTo($was) || $event === 'appointment.rescheduled') {
                $moved = $starts->copy()->subHours($locked->cancellation_hours);
                $locked->forceFill([
                    'refund_until' => match (true) {
                        $changedBy === 'owner' => $moved->isPast() ? $starts->copy() : $moved,
                        default => $moved->lt($locked->refund_until) ? $moved : $locked->refund_until,
                    },
                    'changed_by' => $changedBy === 'guest' ? 'singer' : ($changedBy === 'owner' ? 'coach' : $locked->changed_by),
                ]);
            }

            if ($booking['status'] === 'confirmed' && $locked->status === LessonBooking::PENDING_APPROVAL) {
                $locked->forceFill(['status' => LessonBooking::CONFIRMED]);
                Engagement::query()->whereKey($locked->engagement_id)->where('status', Engagement::REQUESTED)->update(['status' => Engagement::BOOKED, 'accept_by' => null, 'updated_at' => now()]);
            }

            $locked->forceFill(['synced_at' => now()])->save();
        });

        if ($event === 'appointment.completed' || $booking['status'] === 'completed') {
            $this->complete($lesson);
        }
    }

    /**
     * Cancelled from the app, by the singer or the coach, before it starts. Who asked is kept
     * first, so ES's own webhook for it (`changed_by: app`) is settled the same way even if this
     * request goes no further.
     */
    public function cancel(User $user, Engagement $engagement): Engagement
    {
        $lesson = LessonBooking::query()->with('connection')->where('engagement_id', $engagement->id)->first() ?? throw new Refusal(404, 'not_found');

        if (! in_array($engagement->status, [Engagement::REQUESTED, Engagement::BOOKED], true) || $lesson->starts_at->isPast()) {
            throw new Refusal(409, 'engagement_state');
        }

        $by = $user->id === $engagement->coach_id ? 'coach' : 'singer';
        $lesson->forceFill(['cancel_requested_by' => $by])->save();
        $note = $this->cancelInEs($lesson, __('coaching.lesson_cancelled_in_app', ['by' => $by]), rethrowUnavailable: true);
        $this->settleCancel($lesson, $by, now(), null);

        if ($note !== null) {
            $this->problem($lesson, LessonBooking::MISMATCH, $note);
        }

        // As it is now, keeping the people already loaded.
        $engagement->setRawAttributes(Engagement::query()->whereKey($engagement->id)->firstOrFail()->getAttributes(), true);

        return $engagement->setRelation('lesson', $lesson->refresh());
    }

    /**
     * The coach suspended, or the singer's account deleted: the lesson's cancelled in ES too, and
     * refunded in full (the coach's case) or under the coach's policy (the singer's).
     */
    public function end(Engagement $engagement, string $by): void
    {
        $lesson = LessonBooking::query()->with('connection')->where('engagement_id', $engagement->id)->first();

        if ($lesson === null || ! in_array($engagement->status, [Engagement::BOOKING, Engagement::REQUESTED, Engagement::BOOKED], true)) {
            return;
        }

        $lesson->forceFill(['cancel_requested_by' => $by])->save();
        $note = $this->cancelInEs($lesson, null, rethrowUnavailable: false);
        $this->settleCancel($lesson, $by, now(), $by === 'coach' ? 'coach_unavailable' : 'singer_left', force: $lesson->status === LessonBooking::BOOKING);

        if ($note !== null) {
            $this->problem($lesson, LessonBooking::MISMATCH, $note);
        }
    }

    /**
     * Every few minutes (with the coaching deadlines): a lesson waiting too long for the coach's
     * approval is cancelled and refunded; one a day past its end with nothing cancelled is done;
     * a share that couldn't move yet is tried again; a booking whose job was lost is queued again.
     *
     * @return array{refunded: int, completed: int, transferred: int, requeued: int, failed: int}
     */
    public function deadlines(): array
    {
        $done = ['refunded' => 0, 'completed' => 0, 'transferred' => 0, 'requeued' => 0, 'failed' => 0];
        $each = function (callable $step, string $counter) use (&$done): void {
            try {
                $done[$counter] += $step() ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
                $done['failed']++;
            }
        };

        $waiting = LessonBooking::query()->with('connection')->where('status', LessonBooking::PENDING_APPROVAL)
            ->whereHas('engagement', fn ($query) => $query->where('status', Engagement::REQUESTED)->where('accept_by', '<', now()))->get();

        foreach ($waiting as $lesson) {
            $each(function () use ($lesson): bool {
                $lesson->forceFill(['cancel_requested_by' => 'coach'])->save();
                $note = $this->cancelInEs($lesson, __('coaching.lesson_not_approved'), rethrowUnavailable: true);
                $settled = $this->settleCancel($lesson, 'coach', now(), 'no_answer');

                if ($note !== null) {
                    $this->problem($lesson, LessonBooking::MISMATCH, $note);
                }

                return $settled;
            }, 'refunded');
        }

        $over = LessonBooking::query()->where('status', LessonBooking::CONFIRMED)->where('ends_at', '<', now()->subHours((int) config('coaching.lesson_complete_hours')))->get();

        foreach ($over as $lesson) {
            $each(fn (): bool => $this->complete($lesson), 'completed');
        }

        $unpaid = Engagement::query()->where('kind', Engagement::LESSON)->whereNull('stripe_transfer')->whereNull('disputed_at')
            ->where(fn ($query) => $query->where('status', Engagement::DELIVERED)->orWhere(fn ($q) => $q->where('status', Engagement::CANCELLED)->where('decline_reason', 'late_cancel')))
            ->where('ended_at', '>', now()->subDays(30))->get();

        foreach ($unpaid as $engagement) {
            $each(fn (): bool => DB::transaction(fn (): bool => $this->transferShare(Engagement::query()->whereKey($engagement->id)->lockForUpdate()->firstOrFail())), 'transferred');
        }

        $stuck = Engagement::query()->where('kind', Engagement::LESSON)->where('status', Engagement::BOOKING)->where('updated_at', '<', now()->subMinutes(30))->get();

        foreach ($stuck as $engagement) {
            $each(function () use ($engagement): bool {
                BookLesson::dispatch($engagement->id);

                return true;
            }, 'requeued');
        }

        return ['refunded' => $done['refunded'], 'completed' => $done['completed'], 'transferred' => $done['transferred'], 'requeued' => $done['requeued'], 'failed' => $done['failed']];
    }

    /**
     * Hourly: every lesson from 2 days ago to 60 days ahead is asked of ES (the longest unchecked
     * first), and what ES has is applied as if its webhook had come (one was lost: the
     * connection's webhook is marked failing). A lesson ES doesn't have, or that failed to book but
     * ES has, is marked for the health screen. ES down, or the connection refused, leaves the
     * rest of that coach's lessons for the next hour; anything else is reported and passed over.
     *
     * @return array{checked: int, fixed: int, problems: int, skipped: int}
     */
    public function reconcile(): array
    {
        $done = ['checked' => 0, 'fixed' => 0, 'problems' => 0, 'skipped' => 0];
        $lessons = LessonBooking::query()->with(['connection', 'engagement'])->whereNotNull('es_connection_id')
            ->where(fn ($query) => $query->whereIn('status', [LessonBooking::PENDING_APPROVAL, LessonBooking::CONFIRMED])->whereBetween('starts_at', [now()->subDays(2), now()->addDays(60)])
                ->orWhere(fn ($q) => $q->where('status', LessonBooking::FAILED)->where('problem', LessonBooking::BOOKING_FAILED)->where('updated_at', '>', now()->subDays(7))))
            ->orderByRaw('synced_at is not null')->orderBy('synced_at')
            ->get();

        foreach ($lessons->groupBy('es_connection_id') as $group) {
            $connection = $group->first()?->connection;

            if ($connection === null || ! $connection->usable()) {
                $done['skipped'] += $group->count();

                continue;
            }

            foreach ($group->values() as $i => $lesson) {
                try {
                    $result = $this->check1($connection, $lesson);
                } catch (EventScheduleError $e) {
                    report($e);

                    if (in_array($e->reason, [EventScheduleError::UNAVAILABLE, EventScheduleError::RECONNECT], true)) {
                        $done['skipped'] += $group->count() - $i;

                        break;
                    }

                    $done['skipped']++;

                    continue;
                }

                $done['checked']++;

                if ($result !== 'checked') {
                    $done[$result]++;
                }
            }
        }

        // A disconnected account kept only for its lessons goes once they're all past.
        EsConnection::query()->where('status', EsConnection::DISCONNECTED)->whereDoesntHave('lessons', fn ($query) => self::upcoming($query))->delete();

        return $done;
    }

    /**
     * Lessons still to come: booked or being booked, and not yet ended.
     *
     * @template TQuery of Builder<LessonBooking>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function upcoming(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN)->where('ends_at', '>', now())
            ->whereHas('engagement', fn ($q) => $q->whereIn('status', [Engagement::BOOKING, Engagement::REQUESTED, Engagement::BOOKED]));
    }

    /**
     * Until when the singer's own cancellation is refunded (the coach's policy when it was bought).
     */
    public static function refundUntil(LessonBooking $lesson): Carbon
    {
        return $lesson->refund_until;
    }

    /**
     * @return 'checked'|'fixed'|'problems'
     */
    private function check1(EsConnection $connection, LessonBooking $lesson): string
    {
        $sqid = $lesson->engagement->sqid();
        $found = $lesson->es_booking_id !== null ? $this->es->booking($connection, $lesson->es_booking_id) : $this->es->bookingByExternalId($connection, $sqid);

        // Refunded as never booked, but ES has it: cancelled there so the coach isn't expecting them.
        if ($lesson->status === LessonBooking::FAILED) {
            if ($found !== null && $found['status'] !== 'cancelled') {
                $this->es->cancel($connection, $found['id'], __('coaching.lesson_not_booked'));
                $this->problem($lesson, LessonBooking::BOOKING_FAILED, 'It was booked in Event Schedule after it was refunded here; cancelled there.');

                return 'problems';
            }

            return 'checked';
        }

        if ($found === null) {
            $this->problem($lesson, LessonBooking::MISMATCH, 'Event Schedule has no such booking.');

            return 'problems';
        }

        // ES's "completed" before the lesson has ended isn't believed (and isn't a lost webhook).
        $differs = $found['status'] === 'cancelled'
            || ($found['status'] === 'completed' && $lesson->ends_at->isPast())
            || ($found['status'] === 'confirmed' && $lesson->status === LessonBooking::PENDING_APPROVAL)
            || ($found['startsAt'] !== '' && ! Carbon::parse($found['startsAt'])->equalTo($lesson->starts_at));

        if (! $differs) {
            $lesson->forceFill(['synced_at' => now()])->save();

            return 'checked';
        }

        $connection->forceFill(['webhook_failing_at' => $connection->webhook_failing_at ?? now()])->save();
        $this->apply($lesson, $found, $found['status'] === 'cancelled' ? 'appointment.cancelled' : ($found['status'] === 'completed' ? 'appointment.completed' : 'appointment.rescheduled'), null, now());
        $this->problem($lesson->refresh(), LessonBooking::MISMATCH, "Fixed from Event Schedule (it said {$found['status']}, starting {$found['startsAt']}).");

        return 'fixed';
    }

    /**
     * Cancel the booking in ES, if there's one we can reach. ES being down throws when asked to
     * (the person tries again); otherwise what went wrong comes back as a note for Hillel.
     */
    private function cancelInEs(LessonBooking $lesson, ?string $reason, bool $rethrowUnavailable): ?string
    {
        if ($lesson->es_booking_id === null) {
            return null;
        }

        if ($lesson->connection === null || ! $lesson->connection->usable()) {
            return "Couldn't cancel it in Event Schedule (the coach's connection isn't working).";
        }

        try {
            $this->es->cancel($lesson->connection, $lesson->es_booking_id, $reason);
        } catch (EventScheduleError $e) {
            if ($rethrowUnavailable && $e->reason === EventScheduleError::UNAVAILABLE) {
                throw $e;
            }

            report($e);

            return "Couldn't cancel it in Event Schedule ({$e->reason}).";
        }

        return null;
    }

    /**
     * @param  Booking  $booking
     */
    private function booked(LessonBooking $lesson, array $booking): void
    {
        $cancel = DB::transaction(function () use ($lesson, $booking): bool {
            $engagement = Engagement::query()->whereKey($lesson->engagement_id)->lockForUpdate()->firstOrFail();
            $locked = LessonBooking::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            // Already booked (its webhook came first): nothing more. Ended while ES was booking it
            // (cancelled, refunded, the coach suspended): it mustn't stand in ES.
            if ($locked->status !== LessonBooking::BOOKING) {
                return in_array($locked->status, [LessonBooking::CANCELLED, LessonBooking::FAILED], true) && $locked->es_booking_id === null;
            }

            if ($engagement->status !== Engagement::BOOKING) {
                return true;
            }

            $pending = $booking['status'] === 'pending_approval';
            $locked->forceFill([
                'es_booking_id' => $booking['id'],
                'es_event_id' => $booking['eventId'],
                'status' => $pending ? LessonBooking::PENDING_APPROVAL : LessonBooking::CONFIRMED,
                'starts_at' => $booking['startsAt'] !== '' ? Carbon::parse($booking['startsAt']) : $locked->starts_at,
                'ends_at' => $booking['endsAt'] !== '' ? Carbon::parse($booking['endsAt']) : $locked->ends_at,
                'timezone' => $booking['timezone'] !== '' ? $booking['timezone'] : $locked->timezone,
                'manage_url' => self::link($booking['manageUrl']),
                'ical_url' => self::link($booking['icalUrl']),
                'location_url' => self::link($booking['locationUrl']),
                'synced_at' => now(),
            ])->save();

            $acceptBy = now()->addHours((int) config('coaching.accept_hours'));
            $latest = $locked->starts_at->copy()->subHours((int) config('coaching.approval_before_hours'));
            $engagement->forceFill($pending ? ['status' => Engagement::REQUESTED, 'accept_by' => $acceptBy->lt($latest) ? $acceptBy : $latest] : ['status' => Engagement::BOOKED])->save();

            return false;
        });

        $lesson->refresh();

        if ($cancel && $lesson->connection !== null && $booking['id'] !== '') {
            $this->es->cancel($lesson->connection, $booking['id'], __('coaching.lesson_not_booked'));
        }
    }

    /**
     * Never booked (the time went, or ES never answered): refunded in full.
     */
    private function fail(LessonBooking $lesson, string $reason, ?string $problem = null, ?string $note = null): void
    {
        DB::transaction(function () use ($lesson, $reason, $problem, $note): void {
            $engagement = Engagement::query()->whereKey($lesson->engagement_id)->lockForUpdate()->firstOrFail();

            if ($engagement->status !== Engagement::BOOKING) {
                return;
            }

            $this->refundRest($engagement, "lesson-refund-{$engagement->id}-{$engagement->refunded_cents}");
            $engagement->forceFill(['status' => Engagement::REFUNDED, 'decline_reason' => $reason, 'ended_at' => now()])->save();
            $lesson->forceFill(['status' => LessonBooking::FAILED, ...($problem !== null ? ['problem' => $problem, 'problem_note' => $note, 'problem_at' => now()] : [])])->save();
        });
    }

    /**
     * A cancellation, settled once: in full when the coach cancelled (or never approved it), or the
     * singer did so in time; otherwise nothing comes back and the coach's share moves.
     */
    private function settleCancel(LessonBooking $lesson, string $by, Carbon $at, ?string $reason, bool $force = false): bool
    {
        return DB::transaction(function () use ($lesson, $by, $at, $reason, $force): bool {
            $engagement = Engagement::query()->whereKey($lesson->engagement_id)->lockForUpdate()->firstOrFail();
            $locked = LessonBooking::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [LessonBooking::CANCELLED, LessonBooking::COMPLETED, LessonBooking::FAILED], true) || ! in_array($engagement->status, [Engagement::BOOKING, Engagement::REQUESTED, Engagement::BOOKED], true)) {
                return false;
            }

            $inFull = $force || $by === 'coach' || $engagement->status !== Engagement::BOOKED || $at->lte($locked->refund_until);

            if ($inFull) {
                $this->refundRest($engagement, "lesson-refund-{$engagement->id}-{$engagement->refunded_cents}");
                $engagement->forceFill(['status' => Engagement::REFUNDED, 'decline_reason' => $reason ?? ($by === 'coach' ? 'cancelled_by_coach' : 'cancelled'), 'ended_at' => now()])->save();
            } else {
                $engagement->forceFill(['status' => Engagement::CANCELLED, 'decline_reason' => 'late_cancel', 'ended_at' => now()])->save();
                $this->transferShare($engagement);
            }

            $locked->forceFill(['status' => LessonBooking::CANCELLED, 'changed_by' => $by, 'cancelled_at' => $at])->save();
            $lesson->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Done, once it has ended: both when it was to end when bought and when it ends now (ES's
     * word before that isn't taken, nor a move into the past).
     */
    private function complete(LessonBooking $lesson): bool
    {
        return DB::transaction(function () use ($lesson): bool {
            $engagement = Engagement::query()->whereKey($lesson->engagement_id)->lockForUpdate()->firstOrFail();
            $locked = LessonBooking::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LessonBooking::CONFIRMED || $engagement->status !== Engagement::BOOKED || $locked->ends_at->isFuture() || $locked->agreed_ends_at?->isFuture() === true) {
                return false;
            }

            $engagement->forceFill(['status' => Engagement::DELIVERED, 'ended_at' => now()])->save();
            $locked->forceFill(['status' => LessonBooking::COMPLETED, 'completed_at' => now()])->save();
            $this->transferShare($engagement);

            return true;
        });
    }

    /**
     * The coach's share of a lesson that happened (or was cancelled too late), less any refund;
     * once (its key makes a retry the same transfer). Called under the engagement's lock. Stripe
     * failing is reported and left for the deadlines to try again, never undoing the settlement.
     */
    private function transferShare(Engagement $engagement): bool
    {
        if ($engagement->stripe_transfer !== null || $engagement->disputed_at !== null) {
            return false;
        }

        $share = $engagement->price_cents - $engagement->fee_cents - intdiv($engagement->refunded_cents * ($engagement->price_cents - $engagement->fee_cents), max(1, $engagement->price_cents));

        if ($share <= 0) {
            return false;
        }

        try {
            $account = CoachProfile::query()->where('user_id', $engagement->coach_id)->value('stripe_account_id');
            $charge = $engagement->stripe_charge ?? ($engagement->stripe_payment_intent !== null ? $this->stripe->chargeOf($engagement->stripe_payment_intent) : null);

            if (! is_string($account) || $charge === null) {
                throw new RuntimeException("A lesson's share can't move yet (no charge or no account): {$engagement->sqid()}.");
            }

            $transfer = $this->stripe->transfer($share, (string) $engagement->currency, $account, $charge, $engagement->sqid(), "release-lesson-{$engagement->id}");
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $engagement->forceFill(['stripe_charge' => $charge, 'stripe_transfer' => $transfer])->save();

        return true;
    }

    private function refundRest(Engagement $engagement, string $key): void
    {
        $left = $engagement->price_cents - $engagement->refunded_cents;

        if ($left > 0 && $engagement->stripe_payment_intent !== null) {
            $this->stripe->refund($engagement->stripe_payment_intent, $left, $key);
        }

        $engagement->forceFill(['refunded_cents' => $engagement->price_cents]);
    }

    private function problem(LessonBooking $lesson, string $problem, string $note): void
    {
        $lesson->forceFill(['problem' => $problem, 'problem_note' => mb_substr($note, 0, 190), 'problem_at' => now()])->save();
    }

    /**
     * A link from ES given to the app (join, manage, calendar): https only.
     */
    private static function link(?string $url): ?string
    {
        return $url !== null && str_starts_with(strtolower($url), 'https://') && mb_strlen($url) <= 2048 ? $url : null;
    }
}
