<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Actions\Users\DeleteAccount;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\CoachRating;
use App\Models\Engagement;
use App\Models\EngagementReport;
use App\Models\ExercisePack;
use App\Models\PackPurchase;
use App\Models\ReviewItem;
use App\Models\ReviewMedia;
use App\Models\Take;
use App\Models\User;
use App\Support\Trust\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Tests\Support\Coaching;
use Tests\Support\Wav;
use Tests\TestCase;

final class EngagementsTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConnect();
    }

    /**
     * A review bought and paid: [singer, coach, engagement id].
     *
     * @return array{0: User, 1: User, 2: string}
     */
    private function paidReview(): array
    {
        [$coach, $offering] = $this->coach();
        [$singer, $take] = $this->singerWithTake();
        $made = $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'question' => 'My high notes feel strained.', 'pitchTrack' => ['rate' => 100, 'hz' => [220.0, null]], 'scores' => ['pitchPercent' => 82], 'adult' => true]);
        $made->assertCreated();
        $this->paid((string) Engagement::query()->value('stripe_checkout_id'));

        return [$singer, $coach, (string) $made->json('data.id')];
    }

    public function test_a_coach_who_deletes_their_account_inside_unsend_is_still_paid_for_a_delivered_review(): void
    {
        [$coach, $offering] = $this->coach();
        [, $take] = $this->singerWithTake();
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'question' => 'My high notes.', 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->value('stripe_checkout_id'));

        $this->as($coach);
        $review = (string) $this->postJson('/api/engagements/'.$id.'/accept')->assertOk()->json('data.reviews.0.id');
        $this->putJson('/api/reviews/'.$review, ['comments' => [['atSeconds' => 1.5, 'noteIndex' => null, 'text' => 'Lovely onset.']], 'summary' => ['working' => 'Warm tone.', 'toWorkOn' => ['Breath'], 'practise' => 'Four in, four out.'], 'exercises' => [], 'demoMediaId' => null])->assertOk();
        $this->postJson('/api/reviews/'.$review.'/deliver')->assertOk();

        app(DeleteAccount::class)->handle($coach->fresh() ?? $coach, '203.0.113.9');

        $this->assertSame(2125, $this->connect->transfers[0]['amount'] ?? null);
        $this->assertSame([], $this->connect->refunds);
    }

    public function test_a_bought_pack_outlives_its_coach_and_a_rating_its_singer(): void
    {
        [$coach] = $this->coach();
        $bought = ExercisePack::factory()->create(['coach_id' => $coach->id]);
        $unbought = ExercisePack::factory()->create(['coach_id' => $coach->id]);
        $singer = User::factory()->create();
        PackPurchase::query()->create(['pack_id' => $bought->id, 'user_id' => $singer->id]);
        $rated = Engagement::factory()->create(['coach_id' => $coach->id, 'singer_id' => User::factory()->create()->id]);
        $rating = CoachRating::factory()->create(['engagement_id' => $rated->id, 'coach_id' => $coach->id, 'singer_id' => $rated->singer_id, 'stars' => 5]);

        app(DeleteAccount::class)->handle(User::query()->findOrFail($rated->singer_id), '203.0.113.9');
        $this->assertNull($rating->refresh()->singer_id);

        app(DeleteAccount::class)->handle($coach->fresh() ?? $coach, '203.0.113.9');
        $this->assertNull($unbought->fresh());
        $this->assertNull($bought->refresh()->coach_id);
        $this->as($singer);
        $this->getJson('/api/packs/mine')->assertOk()->assertJsonPath('data.0.id', $bought->uuid)->assertJsonPath('data.0.coach.name', 'A deleted account');
    }

    public function test_a_singer_buys_a_review_the_coach_delivers_it_and_their_share_moves_after_unsend(): void
    {
        [$coach, $offering] = $this->coach();
        [$singer, $take] = $this->singerWithTake();

        $made = $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'question' => 'My high notes feel strained.', 'pitchTrack' => ['rate' => 100, 'hz' => [220.0, null]], 'scores' => ['pitchPercent' => 82], 'adult' => true]);
        $this->assertMatchesContract($made, 'engagements.create', 201);
        $made->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.money.feeCents', 375);
        $this->assertSame(['kind' => 'coaching', 'engagement' => $made->json('data.id')], $this->connect->checkouts[0]['metadata']);
        $this->assertNull($this->connect->checkouts[0]['destination']);
        $id = (string) $made->json('data.id');

        $this->paid((string) Engagement::query()->value('stripe_checkout_id'));
        $shown = $this->getJson('/api/engagements/'.$id);
        $this->assertMatchesContract($shown, 'engagements.show', 200);
        $shown->assertJsonPath('data.engagement.status', 'requested')->assertJsonPath('data.reviews.0.review', null);
        $this->assertNotNull($shown->json('data.reviews.0.pitchTrackUrl'));
        $this->assertMatchesContract($this->getJson('/api/engagements'), 'engagements.list', 200);

        $this->as($coach);
        $this->getJson('/api/engagements?as=coach')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.money', null);
        $accepted = $this->postJson('/api/engagements/'.$id.'/accept');
        $this->assertMatchesContract($accepted, 'engagements.accept', 200);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/accept'), 'engagements.accept', 409);
        $review = (string) $accepted->json('data.reviews.0.id');

        // A voice note, uploaded like a take, comes back as AAC.
        $wav = Wav::tone(1.0);
        $media = (string) Str::uuid();
        $started = $this->postJson('/api/reviews/'.$review.'/media', ['id' => $media, 'kind' => 'voice_note', 'sampleRate' => 48000, 'frames' => 48000, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)]);
        $this->assertMatchesContract($started, 'reviews.media.create', 201);
        $this->assertMatchesContract($this->postJson('/api/review-media/'.$media.'/complete'), 'reviews.media.complete', 409);
        Storage::disk('private')->put((string) ReviewMedia::query()->value('upload_key'), $wav);
        $this->assertMatchesContract($this->postJson('/api/review-media/'.$media.'/complete'), 'reviews.media.complete', 200);
        $this->assertSame(ReviewMedia::READY, ReviewMedia::query()->value('status'));

        $draft = ['comments' => [['atSeconds' => 1.5, 'noteIndex' => 2, 'text' => 'Lovely onset.'], ['atSeconds' => 3.0, 'mediaId' => $media]], 'summary' => null, 'exercises' => [['packId' => null, 'exerciseId' => 'breath-4-4']], 'demoMediaId' => null];
        $this->assertMatchesContract($this->putJson('/api/reviews/'.$review, $draft), 'reviews.save', 200);
        $early = $this->postJson('/api/reviews/'.$review.'/deliver');
        $this->assertMatchesContract($early, 'reviews.deliver', 422);
        $this->putJson('/api/reviews/'.$review, [...$draft, 'summary' => ['working' => 'Warm tone.', 'toWorkOn' => ['Breath', 'The top G'], 'practise' => 'Four in, four out.']])->assertOk();
        $delivered = $this->postJson('/api/reviews/'.$review.'/deliver');
        $this->assertMatchesContract($delivered, 'reviews.deliver', 200);

        // Unsend within 10 minutes, then deliver again.
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/unsend'), 'reviews.unsend', 200);
        $this->postJson('/api/reviews/'.$review.'/deliver')->assertOk();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame([], $this->connect->transfers);

        $this->travel(11)->minutes();
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/unsend'), 'reviews.unsend', 409);
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(2125, $this->connect->transfers[0]['amount'] ?? null);
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertCount(1, $this->connect->transfers);

        // The singer sees it, asks one follow-up, the coach answers once.
        $this->as($singer);
        $this->getJson('/api/engagements/'.$id)->assertJsonPath('data.reviews.0.review.summary.working', 'Warm tone.')->assertJsonCount(1, 'data.reviews.0.media');
        $asked = $this->postJson('/api/reviews/'.$review.'/follow-up', ['question' => 'Breathe before "that saved"?']);
        $this->assertMatchesContract($asked, 'reviews.followup', 200);
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/follow-up', ['question' => 'Again?']), 'reviews.followup', 409);
        $this->as($coach);
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/answer', ['text' => 'Yes, a quick one.']), 'reviews.answer', 200);
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/answer', ['text' => 'Again.']), 'reviews.answer', 409);
        $this->assertMatchesContract($this->putJson('/api/reviews/'.$review, $draft), 'reviews.save', 409);

        // A rating, and the coach's one reply.
        $this->as($singer);
        $rated = $this->postJson('/api/engagements/'.$id.'/rating', ['stars' => 5, 'text' => 'Clear and kind.']);
        $this->assertMatchesContract($rated, 'ratings.create', 200);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/rating', ['stars' => 9]), 'ratings.create', 422);
        $this->as($coach);
        $reply = $this->postJson('/api/ratings/'.$rated->json('data.id').'/reply', ['text' => 'Thank you!']);
        $this->assertMatchesContract($reply, 'ratings.reply', 200);
        $this->assertMatchesContract($this->postJson('/api/ratings/'.$rated->json('data.id').'/reply', ['text' => 'Again']), 'ratings.reply', 409);
        $this->assertMatchesContract($this->postJson('/api/ratings/nope/reply', ['text' => 'x']), 'ratings.reply', 404);
    }

    public function test_a_decline_no_answer_a_missed_deadline_or_cancelling_first_refunds_in_full(): void
    {
        [$singer, $coach, $declined] = $this->paidReview();
        $this->as($coach);
        $refused = $this->postJson('/api/engagements/'.$declined.'/decline', ['reason' => "I'm away that week."]);
        $this->assertMatchesContract($refused, 'engagements.decline', 200);
        $refused->assertJsonPath('data.engagement.status', 'refunded');
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$declined.'/decline'), 'engagements.decline', 409);
        $this->assertSame(2500, $this->connect->refunds[0][1]);

        // Cancelling before it's accepted.
        $this->as($singer);
        $second = $this->secondReview($coach);
        $cancelled = $this->postJson('/api/engagements/'.$second.'/cancel');
        $this->assertMatchesContract($cancelled, 'engagements.cancel', 200);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$second.'/cancel'), 'engagements.cancel', 409);

        // No answer in 48 hours.
        $quiet = $this->secondReview($coach);
        $this->travel(49)->hours();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame('no_answer', Engagement::query()->whereSqid($quiet)->value('decline_reason'));

        // Accepted, then late.
        $late = $this->secondReview($coach);
        $this->as($coach);
        $this->postJson('/api/engagements/'.$late.'/accept')->assertOk();
        $this->travel(6)->days();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame(['refunded', 'missed_deadline'], [Engagement::query()->whereSqid($late)->value('status'), Engagement::query()->whereSqid($late)->value('decline_reason')]);
        $this->assertCount(4, $this->connect->refunds);
        $this->assertSame([], $this->connect->transfers);
    }

    private function secondReview(User $coach): string
    {
        $offering = CoachOffering::query()->where('coach_id', $coach->id)->firstOrFail();
        $take = (string) Take::query()->where('user_id', auth()->id())->value('uuid');
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'question' => 'Again?'])->assertCreated()->json('data.id');
        $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));

        return $id;
    }

    public function test_a_package_is_a_monthly_subscription_with_a_number_of_reviews(): void
    {
        [$coach, $offering] = $this->coach(CoachOffering::PACKAGE);
        [$singer, $take] = $this->singerWithTake();

        $made = $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'adult' => true])->assertCreated();
        $checkout = $this->connect->checkouts[0];
        $this->assertSame(['subscription', 15], [$checkout['mode'], $checkout['feePercent']]);
        $this->assertNotNull($checkout['destination']);
        $this->paid((string) $checkout['id']);
        $id = (string) $made->json('data.id');
        $this->getJson('/api/engagements/'.$id)->assertJsonPath('data.engagement.status', 'active')->assertJsonPath('data.engagement.reviewsLeft', 2);

        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/reviews', ['takeId' => (string) Str::uuid()]), 'reviews.create', 422);
        $sent = $this->postJson('/api/engagements/'.$id.'/reviews', ['takeId' => $take, 'question' => 'This one?']);
        $this->assertMatchesContract($sent, 'reviews.create', 201);
        $this->postJson('/api/engagements/'.$id.'/reviews', ['takeId' => $take])->assertCreated();
        $used = $this->postJson('/api/engagements/'.$id.'/reviews', ['takeId' => $take]);
        $this->assertMatchesContract($used, 'reviews.create', 409);
        $used->assertJsonPath('code', 'reviews_used');

        // The next month paid: its reviews start again. Stripe never takes it for Plus.
        $subscription = (string) Engagement::query()->whereSqid($id)->value('stripe_subscription');
        $this->stripeEvent('customer.subscription.created', ['id' => $subscription, 'customer' => 'cus_x', 'status' => 'active', 'metadata' => ['type' => 'coaching', 'kind' => 'coaching'], 'items' => ['data' => []]])->assertOk();
        $this->stripeEvent('invoice.paid', ['id' => 'in_1', 'customer' => 'cus_x', 'subscription' => $subscription, 'lines' => ['data' => [['period' => ['end' => now()->addMonth()->getTimestamp()]]]]])->assertOk();
        $this->assertSame(2, Engagement::query()->whereSqid($id)->value('reviews_left'));
        $this->assertNull($singer->fresh()?->plus_paid_at);
        $this->assertSame(0, Subscription::query()->count());

        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/cancel'), 'engagements.cancel', 200);
        $this->assertSame([$subscription], $this->connect->cancelled);
        $this->stripeEvent('customer.subscription.deleted', ['id' => $subscription, 'customer' => 'cus_x', 'metadata' => ['type' => 'coaching']])->assertOk();
        $this->assertSame('ended', Engagement::query()->whereSqid($id)->value('status'));
        $this->assertSame('ended', $this->getJson('/api/engagements/'.$id)->json('data.engagement.status'));
        $this->assertNotNull($coach);
    }

    public function test_packs_free_at_once_or_paid_with_the_share_moving_at_once(): void
    {
        [$coach] = $this->coach();
        $free = ExercisePack::factory()->create(['coach_id' => $coach->id]);
        $paid = ExercisePack::factory()->create(['coach_id' => $coach->id, 'price_cents' => 900]);
        $this->as(User::factory()->create());

        $this->postJson('/api/engagements', ['packId' => $free->uuid, 'adult' => true])->assertCreated()->assertJsonPath('checkoutUrl', null);
        $mine = $this->getJson('/api/packs/mine');
        $this->assertMatchesContract($mine, 'packs.mine', 200);
        $mine->assertJsonCount(1, 'data');

        $this->postJson('/api/engagements', ['packId' => $paid->uuid])->assertCreated();
        $this->assertSame([135, 'payment'], [$this->connect->checkouts[0]['fee'], $this->connect->checkouts[0]['mode']]);
        $this->paid((string) $this->connect->checkouts[0]['id']);
        $this->getJson('/api/packs/mine')->assertJsonCount(2, 'data');
    }

    public function test_buying_needs_a_coach_who_can_sell_isnt_yourself_and_18_and_over(): void
    {
        [$coach, $offering] = $this->coach();
        [, $take] = $this->singerWithTake();

        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take]), 'engagements.create', 422);
        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => 'nope', 'adult' => true]), 'engagements.create', 404);

        CoachProfile::query()->where('user_id', $coach->id)->update(['charges_enabled' => false]);
        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'adult' => true]), 'engagements.create', 409);
        CoachProfile::query()->where('user_id', $coach->id)->update(['charges_enabled' => true]);

        $this->connect->ready = false;
        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'adult' => true]), 'engagements.create', 423);

        $this->as($coach);
        $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'adult' => true])->assertStatus(409)->assertJsonPath('code', 'own_coaching');
        $this->assertSame(0, Engagement::query()->count());

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'adult' => true]);
        }
        $this->assertMatchesContract($this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'adult' => true]), 'engagements.create', 429);
    }

    public function test_messages_warn_about_paying_outside_the_app_and_reports_reach_hillel_who_can_refund(): void
    {
        [$singer, $coach, $id] = $this->paidReview();

        $sent = $this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'Thanks! Looking forward to it.']);
        $this->assertMatchesContract($sent, 'messages.create', 201);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/messages', []), 'messages.create', 422);
        // Admin's change to the limit applies (it's counted by hand, not on the route).
        $limits = app(Limits::class);
        $limits->override('coach-messages', 'account', 1, $admin = User::factory()->create(), 'Testing');
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'One more']), 'messages.create', 429);
        $limits->override('coach-messages', 'account', null, $admin, 'Done');
        $this->as($coach);
        $this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'Next time just PayPal me, it is cheaper'])->assertCreated()->assertJsonPath('data.warning', 'off_platform');
        $thread = $this->getJson('/api/engagements/'.$id.'/messages');
        $this->assertMatchesContract($thread, 'messages.list', 200);
        $thread->assertJsonCount(2, 'data');

        $this->as($singer);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/report', ['text' => 'The coach asked me to pay outside.']), 'engagements.report', 204);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/report', ['text' => '']), 'engagements.report', 422);
        $this->assertSame(2, EngagementReport::query()->count());

        $this->actingAsAdmin();
        $disputes = $this->getJson('/api/admin/coaching/disputes');
        $this->assertMatchesContract($disputes, 'admin.coaching.disputes.list', 200);
        $disputes->assertJsonCount(2, 'data.0.reports');
        $refund = $this->postJson('/api/admin/coaching/engagements/'.$id.'/refund', ['amountCents' => 1250, 'note' => 'Half back.']);
        $this->assertMatchesContract($refund, 'admin.coaching.refund', 200);
        $this->assertMatchesContract($this->postJson('/api/admin/coaching/engagements/'.$id.'/refund', ['amountCents' => 5000, 'note' => 'Too much.']), 'admin.coaching.refund', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/coaching/engagements/nope/refund', ['amountCents' => 1, 'note' => 'x']), 'admin.coaching.refund', 404);
        $this->assertSame(0, EngagementReport::query()->whereNull('resolved_at')->count());
    }

    public function test_suspending_a_coach_refunds_their_open_reviews_and_neither_side_sees_anyone_elses(): void
    {
        [, $coach, $id] = $this->paidReview();

        $this->as(User::factory()->create());
        $this->assertMatchesContract($this->getJson('/api/engagements/'.$id), 'engagements.show', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/accept'), 'engagements.accept', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/decline'), 'engagements.decline', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/cancel'), 'engagements.cancel', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/report', ['text' => 'x']), 'engagements.report', 404);
        $this->assertMatchesContract($this->getJson('/api/engagements/'.$id.'/messages'), 'messages.list', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'x']), 'messages.create', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/rating', ['stars' => 5]), 'ratings.create', 404);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/reviews', ['takeId' => (string) Str::uuid()]), 'reviews.create', 404);
        $review = (string) ReviewItem::query()->first()?->sqid();
        $this->assertMatchesContract($this->putJson('/api/reviews/'.$review, ['comments' => [], 'exercises' => []]), 'reviews.save', 404);
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/deliver'), 'reviews.deliver', 404);
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/media', ['id' => (string) Str::uuid(), 'kind' => 'demo', 'sampleRate' => 48000, 'frames' => 10, 'bytes' => 64, 'sha256' => str_repeat('a', 64)]), 'reviews.media.create', 404);
        $this->assertMatchesContract($this->postJson('/api/reviews/'.$review.'/media', ['id' => 'x']), 'reviews.media.create', 422);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/rating', []), 'ratings.create', 422);

        $this->actingAsAdmin();
        $this->putJson('/api/admin/coaching/coaches/'.$coach->sqid(), ['status' => 'suspended', 'note' => 'Complaints'])->assertOk();
        $this->assertSame('refunded', Engagement::query()->whereSqid($id)->value('status'));

        // A rating needs a finished engagement.
        $this->as(Engagement::query()->whereSqid($id)->firstOrFail()->singer);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/rating', ['stars' => 4]), 'ratings.create', 409);
        $this->assertMatchesContract($this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'Hello?']), 'messages.create', 409);
    }
}
