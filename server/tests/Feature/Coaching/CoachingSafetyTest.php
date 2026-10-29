<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Actions\Coaching\Engagements;
use App\Actions\Users\DeleteAccount;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\EngagementReport;
use App\Models\ExercisePack;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ReviewItem;
use App\Models\Take;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Coaching;
use Tests\Support\FakeBillingGateway;
use Tests\TestCase;

/**
 * Coaching's money and access, where an independent review found them unsafe.
 */
final class CoachingSafetyTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConnect();
    }

    /**
     * @return array{0: User, 1: User, 2: string}
     */
    private function bought(bool $pay = true): array
    {
        [$coach, $offering] = $this->coach();
        [$singer, $take] = $this->singerWithTake();
        $id = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take, 'question' => 'Help?', 'adult' => true])->assertCreated()->json('data.id');

        if ($pay) {
            $this->paid((string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id'));
        }

        return [$singer, $coach, $id];
    }

    private function delivered(User $coach, string $id): ReviewItem
    {
        $this->as($coach);
        $this->postJson('/api/engagements/'.$id.'/accept')->assertOk();
        $review = ReviewItem::query()->where('engagement_id', Engagement::query()->whereSqid($id)->value('id'))->firstOrFail();
        $this->putJson('/api/reviews/'.$review->sqid(), ['comments' => [], 'summary' => ['working' => 'a', 'toWorkOn' => ['b'], 'practise' => 'c'], 'exercises' => []])->assertOk();
        $this->postJson('/api/reviews/'.$review->sqid().'/deliver')->assertOk();

        return $review;
    }

    public function test_a_retried_refund_is_the_same_refund_and_a_tiny_one_takes_no_share_back(): void
    {
        [, $coach, $id] = $this->bought();
        $this->delivered($coach, $id);
        $this->travel(11)->minutes();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertCount(1, $this->connect->transfers);

        $this->actingAsAdmin();
        $this->postJson('/api/admin/coaching/engagements/'.$id.'/refund', ['amountCents' => 1, 'note' => 'A penny.'])->assertOk();
        $this->assertSame([], $this->connect->reversals);

        // Stripe answered but the database didn't keep it: the same call again changes nothing at Stripe.
        $engagement = Engagement::query()->whereSqid($id)->firstOrFail();
        Engagement::query()->whereKey($engagement->id)->update(['refunded_cents' => 0]);
        $this->postJson('/api/admin/coaching/engagements/'.$id.'/refund', ['amountCents' => 1, 'note' => 'Again.'])->assertOk();
        $this->assertCount(1, $this->connect->refunds);

        $this->postJson('/api/admin/coaching/engagements/'.$id.'/refund', ['amountCents' => 1000, 'note' => 'Half.'])->assertOk();
        $this->assertSame(850, $this->connect->reversals[0][1] ?? null);
    }

    public function test_a_packs_refund_takes_the_coachs_share_and_the_fee_back_and_a_package_refunds_its_last_month(): void
    {
        [$coach] = $this->coach();
        $pack = ExercisePack::factory()->create(['coach_id' => $coach->id, 'price_cents' => 900]);
        $this->as(User::factory()->create());
        $id = (string) $this->postJson('/api/engagements', ['packId' => $pack->uuid, 'adult' => true])->assertCreated()->json('data.id');
        $this->paid((string) $this->connect->checkouts[0]['id']);

        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/coaching/engagements/'.$id.'/refund', ['amountCents' => 900, 'note' => 'Not as described.'])->assertOk();
        $this->assertTrue($this->connect->refunds[0][2]);

        [, $package] = $this->coach(CoachOffering::PACKAGE);
        $this->as(User::factory()->create());
        $monthly = (string) $this->postJson('/api/engagements', ['offeringId' => $package->sqid(), 'adult' => true])->json('data.id');
        $this->paid((string) $this->connect->checkouts[1]['id']);
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/coaching/engagements/'.$monthly.'/refund', ['amountCents' => 2500, 'note' => 'A bad month.'])->assertOk();
        $this->assertSame(['pi_sub_'.$this->connect->checkouts[1]['id'], 2500, true], $this->connect->refunds[1]);
    }

    public function test_paying_after_cancelling_or_for_a_suspended_coach_refunds_at_once(): void
    {
        [$singer, $coach, $id] = $this->bought(pay: false);
        $this->as($singer);
        $this->postJson('/api/engagements/'.$id.'/cancel')->assertOk();
        $session = (string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id');
        $this->assertSame([$session], $this->connect->expired);

        $this->paid($session);
        $this->assertSame(['refunded', 'cancelled'], [Engagement::query()->whereSqid($id)->value('status'), Engagement::query()->whereSqid($id)->value('decline_reason')]);
        $this->assertSame(2500, $this->connect->refunds[0][1]);
        // Redelivered: nothing more.
        $this->paid($session);
        $this->assertCount(1, $this->connect->refunds);

        $offering = CoachOffering::query()->where('coach_id', $coach->id)->firstOrFail();
        $take = (string) Take::query()->where('user_id', $singer->id)->value('uuid');
        $second = (string) $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $take])->json('data.id');
        $coach->forceFill(['suspended_at' => now()])->save();
        $this->paid((string) Engagement::query()->whereSqid($second)->value('stripe_checkout_id'));
        $this->assertSame('coach_unavailable', Engagement::query()->whereSqid($second)->value('decline_reason'));
    }

    public function test_a_delayed_payment_waits_for_stripe_to_say_it_was_paid(): void
    {
        [, , $id] = $this->bought(pay: false);
        $session = (string) Engagement::query()->whereSqid($id)->value('stripe_checkout_id');

        $this->connect->paymentStatus = 'unpaid';
        $this->paid($session);
        $this->assertSame('pending_payment', Engagement::query()->whereSqid($id)->value('status'));

        $this->connect->paymentStatus = 'paid';
        $this->stripeEvent('checkout.session.async_payment_succeeded', ['id' => $session, 'metadata' => ['kind' => 'coaching']])->assertOk();
        $this->assertSame('requested', Engagement::query()->whereSqid($id)->value('status'));
    }

    public function test_a_checkout_left_a_day_is_completed_if_it_was_paid_and_forgotten_if_not(): void
    {
        [, , $lost] = $this->bought(pay: false);
        $this->travel(25)->hours();
        // Stripe's webhook never came, but it was paid: it's completed, not deleted.
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame('requested', Engagement::query()->whereSqid($lost)->value('status'));

        $this->connect->paymentStatus = 'unpaid';
        [, , $never] = $this->bought(pay: false);
        $this->travel(25)->hours();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertNull(Engagement::query()->whereSqid($never)->first());
    }

    public function test_one_failing_refund_doesnt_stop_the_deadlines(): void
    {
        [, $coach, $first] = $this->bought();
        $review = $this->delivered($coach, $first);
        [, , $late] = $this->bought();
        $this->travel(49)->hours();
        $this->connect->failRefunds = true;

        $this->artisan('app:coaching:deadlines')->assertSuccessful();

        $this->assertSame('requested', Engagement::query()->whereSqid($late)->value('status'));
        $this->assertNotNull($review->fresh()?->released_at);
    }

    public function test_a_review_cant_be_delivered_late_and_a_disputed_one_never_pays_out(): void
    {
        [, $coach, $id] = $this->bought();
        $this->as($coach);
        $this->postJson('/api/engagements/'.$id.'/accept')->assertOk();
        $review = ReviewItem::query()->firstOrFail();
        $this->putJson('/api/reviews/'.$review->sqid(), ['comments' => [], 'summary' => ['working' => 'a', 'toWorkOn' => ['b'], 'practise' => 'c'], 'exercises' => []])->assertOk();
        $this->travel(6)->days();
        $this->postJson('/api/reviews/'.$review->sqid().'/deliver')->assertStatus(409);

        [, $coach2, $second] = $this->bought();
        $this->delivered($coach2, $second);
        $this->app->instance(BillingGateway::class, new FakeBillingGateway);
        $this->stripeEvent('charge.dispute.created', ['id' => 'dp_1', 'charge' => Engagement::query()->whereSqid($second)->value('stripe_charge'), 'amount' => 2500, 'status' => 'needs_response'])->assertOk();
        $this->travel(11)->minutes();
        $this->artisan('app:coaching:deadlines')->assertSuccessful();
        $this->assertSame([], $this->connect->transfers);
        $this->assertSame(1, EngagementReport::query()->count());
    }

    public function test_a_review_without_a_known_charge_finds_it_before_paying_the_coach(): void
    {
        $this->connect->charges = false;
        [, $coach, $id] = $this->bought();
        $this->assertNull(Engagement::query()->whereSqid($id)->value('stripe_charge'));
        $this->delivered($coach, $id);
        $this->travel(11)->minutes();

        $this->artisan('app:coaching:deadlines')->assertSuccessful();

        $this->assertStringStartsWith('ch_', (string) ($this->connect->transfers[0]['charge'] ?? ''));
    }

    public function test_a_packages_first_invoice_arriving_early_is_never_plus(): void
    {
        [, $package] = $this->coach(CoachOffering::PACKAGE);
        $singer = $this->as(User::factory()->create());
        $this->postJson('/api/engagements', ['offeringId' => $package->sqid(), 'adult' => true])->assertCreated();

        $this->stripeEvent('invoice.paid', ['id' => 'in_1', 'customer' => 'cus_x', 'subscription' => 'sub_unknown_yet', 'parent' => ['subscription_details' => ['metadata' => ['type' => 'coaching']]], 'lines' => ['data' => []]])->assertOk();

        $this->assertNull($singer->fresh()?->plus_paid_at);
    }

    public function test_only_the_singers_own_voice_can_be_sent(): void
    {
        [$coach, $offering] = $this->coach();
        [$owner, $mine] = $this->singerWithTake();
        $friend = User::factory()->create();
        $project = Project::query()->where('user_id', $owner->id)->firstOrFail();
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $friend->id]);
        Take::query()->where('uuid', $mine)->update(['recorded_by' => $friend->id]);

        // The take is the friend's voice, on the owner's project: the owner can't send it.
        $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $mine, 'adult' => true])->assertStatus(422)->assertJsonValidationErrors('takeId');
        $this->as($friend);
        $this->postJson('/api/engagements', ['offeringId' => $offering->sqid(), 'takeId' => $mine, 'adult' => true])->assertCreated();
        $this->assertNotNull($coach);
    }

    public function test_a_free_pack_opens_no_thread_and_buyers_get_what_was_approved(): void
    {
        [$coach] = $this->coach();
        $pack = ExercisePack::factory()->create(['coach_id' => $coach->id]);
        $this->as(User::factory()->create());
        $id = (string) $this->postJson('/api/engagements', ['packId' => $pack->uuid, 'adult' => true])->json('data.id');
        $this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'Hi!'])->assertStatus(409);

        $pack->forceFill(['exercises' => [['id' => 'new-one', 'title' => 'Not reviewed']], 'status' => ExercisePack::DRAFT])->save();
        $this->getJson('/api/packs/mine')->assertJsonPath('data.0.exercises.0.id', 'breath-4-4');
    }

    public function test_messages_are_limited_per_thread_and_the_coach_loses_access_after_90_days(): void
    {
        config(['limits.coach-messages.account.max' => 2]);
        [$singer, $coach, $id] = $this->bought();
        $this->as($singer);
        $this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'One'])->assertCreated();
        $this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'Two'])->assertCreated();
        $this->postJson('/api/engagements/'.$id.'/messages', ['text' => 'Three'])->assertStatus(429);

        $this->delivered($coach, $id);
        $this->travel(91)->days();
        $this->getJson('/api/engagements/'.$id)->assertNotFound();
        $this->as($singer);
        $this->getJson('/api/engagements/'.$id)->assertOk();
    }

    public function test_deleting_an_account_mid_engagement_refunds_or_pays_and_keeps_the_record(): void
    {
        [$singer, $coach, $open] = $this->bought();
        app(DeleteAccount::class)->handle($singer->fresh() ?? $singer, '203.0.113.1');

        $this->assertSame('refunded', Engagement::query()->whereSqid($open)->value('status'));
        $this->assertNull(Engagement::query()->whereSqid($open)->value('singer_id'));
        $this->assertSame(0, ReviewItem::query()->count());

        [, $package] = $this->coach(CoachOffering::PACKAGE);
        $buyer = $this->as(User::factory()->create());
        $monthly = (string) $this->postJson('/api/engagements', ['offeringId' => $package->sqid(), 'adult' => true])->json('data.id');
        $this->paid((string) end($this->connect->checkouts)['id']);
        $packageCoach = User::query()->findOrFail(CoachOffering::query()->whereKey($package->id)->value('coach_id'));
        app(DeleteAccount::class)->handle($packageCoach, '203.0.113.2');

        $this->assertSame('ended', Engagement::query()->whereSqid($monthly)->value('status'));
        $this->assertNotEmpty($this->connect->cancelledNow);
        $this->assertNotNull($buyer);
        $this->assertNotNull($coach);
    }

    public function test_the_connect_webhook_checks_its_signature_and_production_needs_its_secret(): void
    {
        [$coach] = $this->coach();
        $account = (string) CoachProfile::query()->where('user_id', $coach->id)->value('stripe_account_id');
        CoachProfile::query()->where('user_id', $coach->id)->update(['charges_enabled' => false]);
        $event = (string) json_encode(['id' => 'evt_c1', 'type' => 'account.updated', 'data' => ['object' => ['id' => $account, 'charges_enabled' => true, 'payouts_enabled' => true, 'details_submitted' => true]]]);

        config(['services.stripe_connect.webhook_secret' => 'whsec_connect']);
        $this->call('POST', '/webhooks/stripe-connect', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=nope'], $event)->assertStatus(400);
        $time = time();
        $this->call('POST', '/webhooks/stripe-connect', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$event, 'whsec_connect')], $event)->assertOk();
        $this->assertTrue((bool) CoachProfile::query()->where('user_id', $coach->id)->value('charges_enabled'));

        // A stale event saying charges are on, after Stripe turned them off: Stripe's word now wins.
        $this->connect->account = ['chargesEnabled' => false, 'payoutsEnabled' => false, 'detailsSubmitted' => true];
        $stale = str_replace('evt_c1', 'evt_c0', $event);
        $this->call('POST', '/webhooks/stripe-connect', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$stale, 'whsec_connect')], $stale)->assertOk();
        $this->assertFalse((bool) CoachProfile::query()->where('user_id', $coach->id)->value('charges_enabled'));

        config(['services.stripe_connect.webhook_secret' => null]);
        $this->app['env'] = 'staging';
        $this->call('POST', '/webhooks/stripe-connect', [], [], [], ['CONTENT_TYPE' => 'application/json'], $event)->assertForbidden();
        $this->assertNotNull(app(Engagements::class));
        $this->assertNotNull(Str::uuid());
    }
}
