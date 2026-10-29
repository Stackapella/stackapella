<?php

declare(strict_types=1);

namespace Tests\Feature\EventSchedule;

use App\Actions\Coaching\Lessons;
use App\Jobs\BookLesson;
use App\Jobs\HandleEventScheduleDelivery;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\EsDelivery;
use App\Models\LessonBooking;
use App\Models\User;
use App\Services\EventSchedule\EventScheduleError;
use App\Support\Http\PublicAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\EventScheduling;
use Tests\TestCase;

/**
 * Live lessons, booked in Event Schedule (contract/api.md, "Event Schedule together (M9)").
 */
final class LessonsTest extends TestCase
{
    use EventScheduling;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConnect();
        $this->fakeEventSchedule();
    }

    /**
     * A singer who has bought and paid for a lesson, booked in the fake ES.
     *
     * @return array{0: User, 1: User, 2: EsConnection, 3: Engagement}
     */
    private function bookedLesson(string $type = 'Ap45', int $daysAhead = 3): array
    {
        [$coach, $offering, $connection] = $this->lessonCoach($type);
        $singer = $this->as(User::factory()->create(['name' => 'Ana Ruiz']));
        $slot = $this->freeSlot($connection, $type, $daysAhead);
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $slot, 'timezone' => 'America/New_York', 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));

        return [$coach, $singer, $connection, Engagement::query()->whereSqid($id)->firstOrFail()];
    }

    public function test_a_coach_sells_one_of_their_es_types_as_a_lesson_shown_while_their_connection_works(): void
    {
        [$coach] = $this->coach();
        $this->as($coach);
        $lesson = ['kind' => 'lesson', 'appointmentTypeId' => 'Ap45', 'title' => 'A 45-minute lesson', 'description' => 'On Zoom.', 'priceCents' => 4000, 'currency' => 'gbp'];

        $this->assertMatchesContract($this->postJson('/api/coach/offerings', $lesson), 'coach.offerings.create', 409);
        $connection = $this->esConnection($coach);
        $this->postJson('/api/coach/offerings', [...$lesson, 'appointmentTypeId' => 'Nope'])->assertStatus(422)->assertJsonValidationErrors('appointmentTypeId');
        $this->postJson('/api/coach/offerings', [...$lesson, 'turnaroundDays' => 3])->assertStatus(422);

        $made = $this->postJson('/api/coach/offerings', $lesson);
        $this->assertMatchesContract($made, 'coach.offerings.create', 201);
        $made->assertJsonPath('data.kind', 'lesson')->assertJsonPath('data.durationMinutes', 45)->assertJsonPath('data.turnaroundDays', null);
        $this->getJson('/api/event-schedule/appointment-types')->assertJsonPath('data.0.linked.id', $made->json('data.id'));

        $shown = $this->getJson('/api/coaches/'.$coach->handle);
        $this->assertMatchesContract($shown, 'coaches.show', 200);
        $shown->assertJsonPath('data.offerings.1.kind', 'lesson');

        // ES stopped accepting the connection: the lesson comes off sale.
        $connection->forceFill(['status' => EsConnection::NEEDS_RECONNECT])->save();
        $this->getJson('/api/coaches/'.$coach->handle)->assertJsonCount(1, 'data.offerings');
        $this->assertMatchesContract($this->getJson('/api/coaches/'.$coach->handle.'/offerings/'.$made->json('data.id').'/slots'), 'coaches.slots', 404);
    }

    public function test_a_singer_sees_free_times_in_their_zone_cached_for_30_seconds(): void
    {
        [$coach, $offering, $connection] = $this->lessonCoach();
        $path = '/api/coaches/'.$coach->handle.'/offerings/'.$offering->sqid().'/slots';

        $slots = $this->getJson($path.'?timezone=America/New_York&days=2&from='.now()->addDay()->toDateString());
        $this->assertMatchesContract($slots, 'coaches.slots', 200);
        $first = Carbon::parse((string) $slots->json('data.slots.0.startsAt'));
        $slots->assertJsonPath('data.durationMinutes', 45)->assertJsonPath('data.slots.0.label', $first->setTimezone('America/New_York')->format('g:i A'));

        // Asked again within 30 seconds: from the cache, even while ES is down.
        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->getJson($path.'?timezone=America/New_York&days=2&from='.now()->addDay()->toDateString())->assertOk();
        $this->assertMatchesContract($this->getJson($path.'?days=3'), 'coaches.slots', 424);
        $this->assertMatchesContract($this->getJson($path.'?days=40'), 'coaches.slots', 422);
        $this->assertMatchesContract($this->getJson('/api/coaches/'.$coach->handle.'/offerings/'.CoachOffering::factory()->create(['coach_id' => $coach->id])->sqid().'/slots'), 'coaches.slots', 404);
    }

    public function test_a_singer_pays_for_a_lesson_and_it_is_booked_in_es_as_already_paid(): void
    {
        [$coach, $offering, $connection] = $this->lessonCoach();
        $singer = $this->as(User::factory()->create(['name' => 'Ana Ruiz']));
        $slot = $this->freeSlot($connection);

        $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'adult' => true])->assertStatus(422)->assertJsonValidationErrors('slot');
        $made = $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $slot, 'timezone' => 'America/New_York', 'notes' => 'My break between registers.', 'adult' => true]);
        $this->assertMatchesContract($made, 'engagements.create', 201);
        $made->assertJsonPath('data.kind', 'lesson')->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.lesson.status', 'booking')
            ->assertJsonPath('data.lesson.startsAt', Carbon::parse($slot)->utc()->toIso8601ZuluString())
            ->assertJsonPath('data.lesson.refundUntil', Carbon::parse($slot)->subHours(24)->utc()->toIso8601ZuluString());
        $this->assertSame(4000, $this->connect->checkouts[0]['amount'] ?? 4000);

        $engagement = Engagement::query()->whereSqid((string) $made->json('data.id'))->firstOrFail();
        $this->paid((string) $engagement->stripe_checkout_id);

        $shown = $this->getJson('/api/engagements/'.$engagement->sqid());
        $this->assertMatchesContract($shown, 'engagements.show', 200);
        $shown->assertJsonPath('data.engagement.status', 'booked')->assertJsonPath('data.engagement.lesson.status', 'confirmed')
            ->assertJsonPath('data.engagement.can', ['cancel', 'review', 'message', 'report']);
        $this->assertNotNull($shown->json('data.engagement.lesson.joinUrl'));
        $this->assertNotNull($shown->json('data.engagement.lesson.manageUrl'));

        $booking = array_values($this->es->state()['bookings'])[0];
        $this->assertSame($engagement->sqid(), $booking['externalId']);
        $this->assertSame('Ana Ruiz', $booking['name']);
        $this->assertSame($singer->email, $booking['email']);

        // Paid again (Stripe redelivers): still one booking.
        $this->paid((string) $engagement->stripe_checkout_id);
        $this->assertCount(1, $this->es->state()['bookings']);

        // The coach sees it with what they can do.
        $this->as($coach);
        $this->getJson('/api/engagements?as=coach')->assertJsonPath('data.0.lesson.status', 'confirmed')->assertJsonPath('data.0.money', null)->assertJsonPath('data.0.can', ['cancel', 'deliver', 'message', 'report']);
    }

    public function test_a_time_gone_before_paying_or_before_booking_is_never_kept_and_refunded_in_full(): void
    {
        [, $offering, $connection] = $this->lessonCoach();
        $this->as(User::factory()->create());
        $slot = $this->freeSlot($connection);

        $this->es->take($slot);
        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $slot, 'adult' => true]), 'engagements.create', 409);
        $later = $this->freeSlot($connection, 'Ap45', 4);
        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $later, 'adult' => true]), 'engagements.create', 424);

        // Free when asked, gone by the time it's paid.
        $other = $this->freeSlot($connection, 'Ap45', 5);
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $other, 'adult' => true])->assertCreated()->json('data.id');
        $this->es->take($other);
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));

        $this->getJson('/api/engagements/'.$id)->assertJsonPath('data.engagement.status', 'refunded')->assertJsonPath('data.engagement.declineReason', 'slot_taken')
            ->assertJsonPath('data.engagement.lesson.status', 'failed');
        $this->assertSame(4000, $this->connect->refunds[0][1]);
    }

    public function test_a_booking_es_never_takes_is_tried_again_then_refunded_unless_es_had_it_all_along(): void
    {
        Queue::fake();
        [, $offering, $connection] = $this->lessonCoach();
        $this->as(User::factory()->create());
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $this->freeSlot($connection), 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));
        Queue::assertPushed(BookLesson::class);
        $engagement = Engagement::query()->whereSqid($id)->firstOrFail();
        $this->assertSame(Engagement::BOOKING, $engagement->status);

        $job = new BookLesson($engagement->id);
        $this->es->failNext(EventScheduleError::UNAVAILABLE);

        try {
            $job->handle(app(Lessons::class));
            $this->fail('ES being down should make the job try again.');
        } catch (EventScheduleError $e) {
            $this->assertSame(EventScheduleError::UNAVAILABLE, $e->reason);
        }

        $job->failed(new RuntimeException('Event Schedule never answered.'));
        $engagement->refresh();
        $this->assertSame(Engagement::REFUNDED, $engagement->status);
        $this->assertSame('booking_failed', $engagement->decline_reason);
        $this->assertSame(LessonBooking::BOOKING_FAILED, LessonBooking::query()->value('problem'));

        // Another, where ES booked it but its answer was lost: it's booked after all.
        $second = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $this->freeSlot($connection, 'Ap45', 6), 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($second)->value('stripe_checkout_id'));
        $paid = Engagement::query()->whereSqid($second)->firstOrFail();
        $lesson = LessonBooking::query()->where('engagement_id', $paid->id)->firstOrFail();
        $this->es->book($connection, 'Ap45', ['externalId' => $second, 'slot' => $lesson->starts_at->utc()->toIso8601ZuluString(), 'name' => 'x', 'email' => 'x@example.com', 'guestTimezone' => 'UTC', 'notes' => null], 'k');
        (new BookLesson($paid->id))->failed(null);
        $this->assertSame(Engagement::BOOKED, $paid->refresh()->status);
    }

    public function test_a_coach_cancelling_in_es_refunds_in_full_and_a_redelivery_does_nothing_more(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');
        $this->es->set($bookingId, ['status' => 'cancelled']);

        $this->esWebhook($connection, 'appointment.cancelled', $bookingId, 'owner', deliveryId: 'dl_1')->assertOk();
        $this->esWebhook($connection, 'appointment.cancelled', $bookingId, 'owner', deliveryId: 'dl_1')->assertOk();

        $engagement->refresh();
        $this->assertSame(Engagement::REFUNDED, $engagement->status);
        $this->assertSame('cancelled_by_coach', $engagement->decline_reason);
        $this->assertCount(1, $this->connect->refunds);
        $this->assertSame([4000], array_column($this->connect->refunds, 1));
        $this->getJson('/api/engagements/'.$engagement->sqid())->assertJsonPath('data.engagement.lesson.status', 'cancelled')->assertJsonPath('data.engagement.lesson.changedBy', 'coach');
        $this->assertDatabaseCount('es_deliveries', 1);
    }

    public function test_the_singer_cancelling_in_time_is_refunded_and_too_late_the_coach_is_paid(): void
    {
        [, , $connection, $early] = $this->bookedLesson('Ap45', 3);
        $earlyBooking = (string) LessonBooking::query()->where('engagement_id', $early->id)->value('es_booking_id');
        $this->es->set($earlyBooking, ['status' => 'cancelled']);
        $this->esWebhook($connection, 'appointment.cancelled', $earlyBooking, 'guest')->assertOk();
        $this->assertSame(Engagement::REFUNDED, $early->refresh()->status);
        $this->assertSame('cancelled', $early->decline_reason);

        // A later one, cancelled by the singer 2 hours before it starts.
        [$coach, $offering] = [User::query()->findOrFail($early->coach_id), CoachOffering::query()->where('kind', CoachOffering::LESSON)->firstOrFail()];
        $slot = $this->freeSlot($connection, 'Ap45', 1);
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $slot, 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));
        $late = Engagement::query()->whereSqid($id)->firstOrFail();
        $lateBooking = (string) LessonBooking::query()->where('engagement_id', $late->id)->value('es_booking_id');

        $this->travelTo(Carbon::parse($slot)->subHours(2));
        $this->es->set($lateBooking, ['status' => 'cancelled']);
        $this->esWebhook($connection, 'appointment.cancelled', $lateBooking, 'guest')->assertOk();

        $late->refresh();
        $this->assertSame(Engagement::CANCELLED, $late->status);
        $this->assertSame('late_cancel', $late->decline_reason);
        $this->assertCount(1, $this->connect->refunds);
        $this->assertSame(4000 - 600, $this->connect->transfers[0]['amount']);
        $this->assertSame((string) CoachProfile::query()->where('user_id', $coach->id)->value('stripe_account_id'), $this->connect->transfers[0]['destination']);
    }

    public function test_either_side_cancels_in_the_app_and_es_is_told(): void
    {
        [$coach, $singer, , $engagement] = $this->bookedLesson();

        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$engagement->sqid().'/cancel'), 'engagements.cancel', 424);
        $this->assertSame(Engagement::BOOKED, $engagement->refresh()->status);

        $this->as($coach);
        $cancelled = $this->postJson('/api/engagements/'.$engagement->sqid().'/cancel');
        $this->assertMatchesContract($cancelled, 'engagements.cancel', 200);
        $cancelled->assertJsonPath('data.engagement.status', 'refunded')->assertJsonPath('data.engagement.lesson.changedBy', 'coach');
        $this->assertSame('cancelled', array_values($this->es->state()['bookings'])[0]['status']);
        $this->assertSame([4000], array_column($this->connect->refunds, 1));

        $this->as($singer);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$engagement->sqid().'/cancel'), 'engagements.cancel', 409);
    }

    public function test_a_finished_lesson_pays_the_coach_and_can_be_rated_and_a_reschedule_moves_it(): void
    {
        [, $singer, $connection, $engagement] = $this->bookedLesson();
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');

        // One take before it, no more.
        [, $take] = [null, $this->singerTake($singer)];
        $sent = $this->postJson('/api/engagements/'.$engagement->sqid().'/reviews', ['takeId' => $take, 'question' => 'My break.']);
        $this->assertMatchesContract($sent, 'reviews.create', 201);
        $this->postJson('/api/engagements/'.$engagement->sqid().'/reviews', ['takeId' => $take])->assertStatus(409)->assertJsonPath('code', 'reviews_used');

        $moved = Carbon::parse((string) $this->es->state()['bookings'][$bookingId]['startsAt'])->addDay();
        $this->es->set($bookingId, ['startsAt' => $moved->toIso8601ZuluString(), 'endsAt' => $moved->copy()->addMinutes(45)->toIso8601ZuluString()]);
        $this->esWebhook($connection, 'appointment.rescheduled', $bookingId, 'guest')->assertOk();
        $this->getJson('/api/engagements/'.$engagement->sqid())->assertJsonPath('data.engagement.lesson.startsAt', $moved->toIso8601ZuluString())->assertJsonPath('data.engagement.lesson.changedBy', 'singer');

        $this->assertSame(409, $this->postJson('/api/engagements/'.$engagement->sqid().'/rating', ['stars' => 5])->status());
        $this->travelTo($moved->copy()->addMinutes(50));
        $this->es->set($bookingId, ['status' => 'completed']);
        $this->esWebhook($connection, 'appointment.completed', $bookingId)->assertOk();

        $this->assertSame(Engagement::DELIVERED, $engagement->refresh()->status);
        $this->assertSame([3400], array_column($this->connect->transfers, 'amount'));
        $this->postJson('/api/engagements/'.$engagement->sqid().'/rating', ['stars' => 5, 'text' => 'Lovely.'])->assertSuccessful();

        // Its share moves once, whatever arrives after.
        $this->esWebhook($connection, 'appointment.completed', $bookingId)->assertOk();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertCount(1, $this->connect->transfers);
    }

    public function test_a_booking_cant_name_another_lesson_and_a_failed_delivery_is_handled_again(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $lesson = LessonBooking::query()->firstOrFail();
        $other = LessonBooking::factory()->create(['es_connection_id' => $connection->id]);
        $otherEngagement = Engagement::query()->findOrFail($other->engagement_id);

        // A booking of this lesson whose external_id names the other lesson's engagement.
        $this->es->set((string) $lesson->es_booking_id, ['externalId' => $otherEngagement->sqid(), 'status' => 'cancelled']);
        $this->esWebhook($connection, 'appointment.cancelled', (string) $lesson->es_booking_id, 'owner')->assertOk();
        $this->assertSame(LessonBooking::CONFIRMED, $other->refresh()->status);
        $this->assertSame(LessonBooking::CANCELLED, $lesson->refresh()->status);
        $this->assertSame(Engagement::REFUNDED, $engagement->refresh()->status);

        // Every try failed: Event Schedule's redelivery isn't answered "already handled".
        EsDelivery::query()->forceCreate(['es_connection_id' => $connection->id, 'delivery_id' => 'dl_failed', 'type' => 'appointment.cancelled']);
        (new HandleEventScheduleDelivery($connection->id, ['id' => 'dl_failed']))->failed(null);
        $this->assertFalse(EsDelivery::query()->where('delivery_id', 'dl_failed')->exists());
    }

    public function test_a_lesson_moved_into_the_past_and_marked_done_isnt_paid_before_its_agreed_end(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $lesson = LessonBooking::query()->firstOrFail();
        $bookingId = (string) $lesson->es_booking_id;
        $agreedEnd = $lesson->ends_at->copy();

        // The coach moves it to yesterday, and says it's done.
        $yesterday = now()->subDay()->startOfHour();
        $this->es->set($bookingId, ['startsAt' => $yesterday->toIso8601ZuluString(), 'endsAt' => $yesterday->copy()->addMinutes(45)->toIso8601ZuluString(), 'status' => 'completed']);
        $this->esWebhook($connection, 'appointment.completed', $bookingId, 'owner')->assertOk();

        $this->assertSame(Engagement::BOOKED, $engagement->refresh()->status);
        $this->assertSame([], $this->connect->transfers);
        // With less notice than the policy, the singer may cancel in full until it starts.
        $this->assertTrue($lesson->refresh()->refund_until->equalTo($yesterday));

        // Once the time it was bought for has passed, it counts as done.
        $this->travelTo($agreedEnd->copy()->addHours((int) config('coaching.lesson_complete_hours') + 1));
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(Engagement::DELIVERED, $engagement->refresh()->status);
        $this->assertCount(1, $this->connect->transfers);
    }

    public function test_a_lesson_nobody_cancelled_counts_as_done_a_day_after_it_ends(): void
    {
        [, , , $engagement] = $this->bookedLesson();
        $lesson = LessonBooking::query()->firstOrFail();

        $this->travelTo($lesson->ends_at->copy()->addHours(23));
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(Engagement::BOOKED, $engagement->refresh()->status);

        $this->travelTo($lesson->ends_at->copy()->addHours(25));
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(Engagement::DELIVERED, $engagement->refresh()->status);
        $this->assertCount(1, $this->connect->transfers);
    }

    public function test_a_lesson_needing_approval_waits_for_the_coach_then_is_cancelled_and_refunded(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson('Ap20', 5);
        $this->getJson('/api/engagements/'.$engagement->sqid())->assertJsonPath('data.engagement.status', 'requested')->assertJsonPath('data.engagement.lesson.status', 'pending_approval');
        $this->assertTrue($engagement->refresh()->accept_by?->lte(now()->addHours(48)));

        // Approved in ES: booked.
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');
        $this->es->set($bookingId, ['status' => 'confirmed']);
        $this->esWebhook($connection, 'appointment.confirmed', $bookingId, 'owner')->assertOk();
        $this->assertSame(Engagement::BOOKED, $engagement->refresh()->status);

        // Another that's never approved.
        $offering = CoachOffering::query()->where('kind', CoachOffering::LESSON)->firstOrFail();
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'slot' => $this->freeSlot($connection, 'Ap20', 6), 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));
        $this->travel(49)->hours();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();

        $waited = Engagement::query()->whereSqid($id)->firstOrFail();
        $this->assertSame(Engagement::REFUNDED, $waited->status);
        $this->assertSame('no_answer', $waited->decline_reason);
        $this->assertSame('cancelled', $this->es->state()['bookings'][(string) LessonBooking::query()->where('engagement_id', $waited->id)->value('es_booking_id')]['status']);
    }

    public function test_es_webhooks_are_signed_recent_and_only_touch_that_coachs_own_lessons(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');
        $this->es->set($bookingId, ['status' => 'cancelled']);

        $this->esWebhook($connection, 'appointment.cancelled', $bookingId, 'owner', secret: 'wrong')->assertStatus(400);
        $this->esWebhook($connection, 'appointment.cancelled', $bookingId, 'owner', at: now()->subMinutes(6)->getTimestamp())->assertStatus(400);
        $this->postJson('/webhooks/event-schedule/nope', [])->assertNotFound();

        // Another coach's webhook naming this lesson changes nothing.
        [$other] = $this->coach();
        $theirs = $this->esConnection($other);
        $this->esWebhook($theirs, 'appointment.cancelled', $bookingId, 'owner')->assertOk();
        $this->assertSame(Engagement::BOOKED, $engagement->refresh()->status);
        $this->assertSame([], $this->connect->refunds);
    }

    public function test_the_reconcile_applies_what_a_lost_webhook_missed_and_shows_hillel_what_doesnt_match(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');
        $this->es->set($bookingId, ['status' => 'cancelled']);

        $this->artisan('app:event-schedule:reconcile')->assertSuccessful();
        $this->assertSame(Engagement::REFUNDED, $engagement->refresh()->status);
        $this->assertNotNull($connection->refresh()->webhook_failing_at);
        $this->assertSame(LessonBooking::MISMATCH, LessonBooking::query()->value('problem'));

        // One ES has never heard of.
        [, , , $second] = $this->bookedLesson('Ap45', 4);
        $lesson = LessonBooking::query()->where('engagement_id', $second->id)->firstOrFail();
        $lesson->forceFill(['es_booking_id' => 'Bk-gone'])->save();
        $this->artisan('app:event-schedule:reconcile')->assertSuccessful();
        $this->assertSame('Event Schedule has no such booking.', $lesson->refresh()->problem_note);

        $admin = $this->actingAsAdmin();
        $health = $this->getJson('/api/admin/event-schedule');
        $this->assertMatchesContract($health, 'admin.eventSchedule.show', 200);
        $health->assertJsonPath('data.fake', true)->assertJsonPath('data.connections.webhooksFailing', 1)->assertJsonCount(2, 'data.lessons');
        $this->assertNotNull($admin);
    }

    public function test_a_suspended_coachs_lessons_are_cancelled_and_refunded(): void
    {
        [$coach, , , $engagement] = $this->bookedLesson();
        $this->actingAsAdmin();

        $this->putJson('/api/admin/coaching/coaches/'.$coach->sqid(), ['status' => 'suspended', 'note' => 'Testing.'])->assertOk();

        $this->assertSame(Engagement::REFUNDED, $engagement->refresh()->status);
        $this->assertSame('coach_unavailable', $engagement->decline_reason);
        $this->assertSame('cancelled', array_values($this->es->state()['bookings'])[0]['status']);
    }

    public function test_es_saying_a_lesson_is_done_before_it_ends_pays_nobody_and_a_cancellation_still_refunds(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');

        $this->es->set($bookingId, ['status' => 'completed']);
        $this->esWebhook($connection, 'appointment.completed', $bookingId, 'owner')->assertOk();
        $this->artisan('app:event-schedule:reconcile')->assertSuccessful();
        $this->assertSame(Engagement::BOOKED, $engagement->refresh()->status);
        $this->assertSame([], $this->connect->transfers);
        $this->assertNull(LessonBooking::query()->value('problem'));

        $this->es->set($bookingId, ['status' => 'cancelled']);
        $this->esWebhook($connection, 'appointment.cancelled', $bookingId, 'owner')->assertOk();
        $this->assertSame(Engagement::REFUNDED, $engagement->refresh()->status);
        $this->assertSame([4000], array_column($this->connect->refunds, 1));

        // After its end, ES's word counts.
        [, , , $later] = $this->bookedLesson('Ap45', 4);
        $lesson = LessonBooking::query()->where('engagement_id', $later->id)->firstOrFail();
        $this->es->set((string) $lesson->es_booking_id, ['status' => 'completed']);
        $this->travelTo($lesson->ends_at->copy()->addMinutes(5));
        $this->esWebhook($lesson->connection ?? $connection, 'appointment.completed', (string) $lesson->es_booking_id)->assertOk();
        $this->assertSame(Engagement::DELIVERED, $later->refresh()->status);
    }

    public function test_a_lesson_is_never_accepted_here_and_a_cancellation_we_asked_for_is_settled_as_asked(): void
    {
        [$coach, $singer, $connection, $engagement] = $this->bookedLesson('Ap20', 5);
        $this->as($coach);
        $this->postJson('/api/engagements/'.$engagement->sqid().'/accept')->assertStatus(409)->assertJsonPath('code', 'engagement_state');

        // The singer cancels too late; ES's "app" webhook arrives after (and our settle never ran).
        [, , , $late] = $this->bookedLesson('Ap45', 1);
        $lesson = LessonBooking::query()->where('engagement_id', $late->id)->firstOrFail();
        $this->travelTo($lesson->starts_at->copy()->subHours(2));
        $lesson->forceFill(['cancel_requested_by' => 'singer'])->save();
        $this->es->set((string) $lesson->es_booking_id, ['status' => 'cancelled']);
        $this->esWebhook($lesson->connection ?? $connection, 'appointment.cancelled', (string) $lesson->es_booking_id, 'app')->assertOk();
        $this->assertSame(Engagement::CANCELLED, $late->refresh()->status);
        $this->assertSame('late_cancel', $late->decline_reason);
        $this->assertNotNull($singer);
    }

    public function test_on_a_selfhosted_install_es_isnt_believed_about_who_cancelled(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson('Ap45', 1);
        $connection->forceFill(['mode' => EsConnection::API_KEY])->save();
        $lesson = LessonBooking::query()->firstOrFail();
        $this->travelTo($lesson->starts_at->copy()->subHours(2));
        $this->es->set((string) $lesson->es_booking_id, ['status' => 'cancelled']);

        $this->esWebhook($connection, 'appointment.cancelled', (string) $lesson->es_booking_id, 'guest')->assertOk();

        $this->assertSame(Engagement::REFUNDED, $engagement->refresh()->status);
        $this->assertSame([], $this->connect->transfers);
    }

    public function test_the_singers_reschedule_cant_push_the_refund_deadline_later_but_the_coachs_can(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson('Ap45', 3);
        $lesson = LessonBooking::query()->firstOrFail();
        $deadline = $lesson->refund_until->copy();
        $bookingId = (string) $lesson->es_booking_id;

        $later = $lesson->starts_at->copy()->addDays(2);
        $this->es->set($bookingId, ['startsAt' => $later->toIso8601ZuluString(), 'endsAt' => $later->copy()->addMinutes(45)->toIso8601ZuluString()]);
        $this->esWebhook($connection, 'appointment.rescheduled', $bookingId, 'guest')->assertOk();
        $this->assertTrue($lesson->refresh()->refund_until->equalTo($deadline));
        $this->getJson('/api/engagements/'.$engagement->sqid())->assertJsonPath('data.engagement.lesson.refundUntil', $deadline->utc()->toIso8601ZuluString());

        $this->esWebhook($connection, 'appointment.rescheduled', $bookingId, 'owner')->assertOk();
        $this->assertTrue($lesson->refresh()->refund_until->equalTo($later->copy()->subHours(24)));
    }

    public function test_a_coach_cant_switch_account_or_schedule_away_from_lessons_still_to_come(): void
    {
        [$coach, , $connection] = $this->bookedLesson();
        $this->as($coach);

        $this->assertMatchesContract($this->patchJson('/api/event-schedule/connection', ['schedule' => 'riverside-choir']), 'eventSchedule.connection.update', 409);
        $connection->forceFill(['es_user_id' => 'someone-else'])->save();
        $started = $this->postJson('/api/event-schedule/connect');
        parse_str((string) parse_url((string) $started->json('data.url'), PHP_URL_QUERY), $back);
        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']]), 'eventSchedule.connect.finish', 409);
        PublicAddress::$resolver = fn (string $host): array => ['93.184.216.34'];
        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://events.example.org', 'apiKey' => 'es-abcdefgh']), 'eventSchedule.connect.key', 409);
        PublicAddress::$resolver = null;
        $this->assertNotNull($connection->refresh()->webhook_secret);

        // With nothing to come, a schedule switch takes the old schedule's lessons off sale.
        LessonBooking::query()->update(['status' => LessonBooking::COMPLETED]);
        $this->patchJson('/api/event-schedule/connection', ['schedule' => 'riverside-choir'])->assertOk();
        $this->getJson('/api/coaches/'.$coach->handle)->assertJsonCount(1, 'data.offerings')->assertJsonPath('data.offerings.0.kind', 'review');
    }

    public function test_a_lesson_ended_while_es_was_booking_it_is_cancelled_there(): void
    {
        [, $offering, $connection] = $this->lessonCoach();
        $engagement = Engagement::factory()->create(['kind' => Engagement::LESSON, 'status' => Engagement::REFUNDED, 'coach_id' => $offering->coach_id, 'offering_id' => $offering->id]);
        $lesson = LessonBooking::factory()->create(['engagement_id' => $engagement->id, 'es_connection_id' => $connection->id, 'es_booking_id' => null, 'status' => LessonBooking::CANCELLED]);
        $made = $this->es->book($connection, 'Ap45', ['externalId' => $engagement->sqid(), 'slot' => $this->freeSlot($connection), 'name' => 'x', 'email' => 'x@example.com', 'guestTimezone' => 'UTC', 'notes' => null], 'k');

        (new \ReflectionMethod(Lessons::class, 'booked'))->invoke(app(Lessons::class), $lesson, $made);

        $this->assertSame('cancelled', $this->es->state()['bookings'][$made['id']]['status']);
    }

    public function test_a_waiting_lesson_es_no_longer_has_is_still_refunded_and_a_failed_transfer_is_tried_again(): void
    {
        [$coach, , , $engagement] = $this->bookedLesson('Ap20', 5);
        $this->es->failNext(EventScheduleError::NOT_FOUND);
        $this->travel(49)->hours();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(Engagement::REFUNDED, $engagement->refresh()->status);
        $this->assertSame(LessonBooking::MISMATCH, LessonBooking::query()->value('problem'));

        // The coach's account is missing when the lesson is done: done anyway, paid once it's back.
        [, , , $done] = $this->bookedLesson('Ap45', 3);
        $account = CoachProfile::query()->where('user_id', $done->coach_id)->value('stripe_account_id');
        CoachProfile::query()->where('user_id', $done->coach_id)->update(['stripe_account_id' => null]);
        $this->travelTo(LessonBooking::query()->where('engagement_id', $done->id)->firstOrFail()->ends_at->copy()->addHours(25));
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(Engagement::DELIVERED, $done->refresh()->status);
        $this->assertSame([], $this->connect->transfers);

        CoachProfile::query()->where('user_id', $done->coach_id)->update(['stripe_account_id' => $account]);
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertCount(1, $this->connect->transfers);
        $this->assertNotNull($coach);
    }

    public function test_only_https_links_from_es_reach_the_app(): void
    {
        [, , $connection, $engagement] = $this->bookedLesson();
        $bookingId = (string) LessonBooking::query()->value('es_booking_id');
        $this->es->set($bookingId, ['locationUrl' => 'javascript:alert(1)', 'manageUrl' => 'http://es.example.com/m']);

        $this->esWebhook($connection, 'appointment.confirmed', $bookingId, 'owner')->assertOk();

        $shown = $this->getJson('/api/engagements/'.$engagement->sqid());
        $this->assertStringStartsWith('https://', (string) $shown->json('data.engagement.lesson.joinUrl'));
        $this->assertStringStartsWith('https://', (string) $shown->json('data.engagement.lesson.manageUrl'));
    }

    protected function tearDown(): void
    {
        PublicAddress::$resolver = null;

        parent::tearDown();
    }

    private function singerTake(User $singer): string
    {
        $this->as($singer);
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();

        return $take->uuid;
    }
}
