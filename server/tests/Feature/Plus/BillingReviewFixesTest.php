<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Support\Accounts\SignInSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\Support\Plus;
use Tests\TestCase;

/**
 * What M5's independent review found, each kept fixed.
 */
final class BillingReviewFixesTest extends TestCase
{
    use CloudSave;
    use Plus;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStripe();
        Mail::fake();
    }

    public function test_webhooks_are_signed_and_never_taken_unsigned_in_production(): void
    {
        $user = $this->member();
        $user->forceFill(['stripe_id' => 'cus_1'])->save();
        config(['cashier.webhook.secret' => 'whsec_test']);

        $this->stripeEvent('customer.subscription.created', $this->subscription($user))->assertForbidden();

        $payload = (string) json_encode(['id' => 'evt_1', 'type' => 'customer.subscription.created', 'data' => ['object' => $this->subscription($user)]]);
        $time = time();
        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, 'whsec_test')], $payload)->assertOk();
        $this->assertTrue($this->fresh($user)->subscribed('default'));

        config(['cashier.webhook.secret' => '']);
        $this->app['env'] = 'staging';
        $this->stripeEvent('customer.subscription.created', $this->subscription($user))->assertForbidden();
    }

    public function test_a_redelivered_event_never_cancels_the_persons_own_trial(): void
    {
        $user = $this->member();
        $this->subscribe($user, 'trialing');
        $event = ['id' => 'evt_same', 'type' => 'customer.subscription.created', 'data' => ['object' => $this->subscription($this->fresh($user), 'trialing')]];

        $this->postJson('/webhooks/stripe', $event)->assertOk();
        $this->postJson('/webhooks/stripe', $event)->assertOk();
        $this->stripeEvent('customer.subscription.created', $this->subscription($this->fresh($user), 'trialing'))->assertOk();

        $this->assertSame([], $this->stripe->cancelled);
        $this->assertTrue($this->fresh($user)->subscribed('default'));
    }

    public function test_a_second_trial_on_one_account_is_cancelled(): void
    {
        $user = $this->member();
        $this->subscribe($user, 'trialing');

        $this->stripeEvent('customer.subscription.created', $this->subscription($this->fresh($user), 'trialing', ['id' => 'sub_second', 'items' => ['data' => [['id' => 'si_second', 'quantity' => 1, 'price' => ['id' => 'price_year', 'product' => 'prod_plus']]]]]))->assertOk();

        $this->assertSame(['sub_second'], $this->stripe->cancelled);
    }

    public function test_deleting_the_account_stops_the_subscription(): void
    {
        $user = $this->member();
        $this->subscribe($user);
        $this->actingAs($this->fresh($user))->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp()]);

        $this->deleteJson('/api/account')->assertNoContent();

        $this->assertSame(['sub_'.$user->id], $this->stripe->cancelled);
        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
    }

    public function test_the_plan_shown_is_the_subscriptions_after_a_portal_switch(): void
    {
        config(['billing.prices' => ['month' => 'price_month', 'year' => 'price_year']]);
        $user = $this->member();
        $this->actingAs($user);
        $this->postJson('/api/billing/checkout', ['interval' => 'month', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings'])->assertCreated();
        $this->subscribe($this->fresh($user));

        // The subscription is on the yearly price (the helper's), whatever the Checkout said.
        $this->actingAs($this->fresh($user));
        $this->getJson('/api/billing')->assertJsonPath('data.subscription.interval', 'year')->assertJsonPath('data.subscription.amountCents', 3999);
    }

    public function test_after_a_downgrade_editing_doesnt_move_the_line_and_parts_cant_be_deleted(): void
    {
        $user = $this->member();
        $this->subscribe($user);
        $this->actingAs($this->fresh($user));
        $ids = [];
        foreach (range(1, 4) as $i) {
            $ids[] = $id = (string) Str::uuid();
            $this->saveProject($id)->assertOk();
            $this->travel(1)->minutes();
        }
        $part = (string) Str::uuid();
        $this->savePart($part, $ids[0])->assertOk();

        $this->stripeEvent('customer.subscription.deleted', $this->subscription($this->fresh($user), 'canceled'))->assertOk();
        $this->actingAs($this->fresh($user));

        // Its practice settings save, and it stays read-only.
        $this->saveProject($ids[0], 1, ['settings' => ['practice' => ['x' => 1]]])->assertOk();
        $this->actingAs($this->fresh($user));
        $this->assertSame('limit', collect((array) $this->pull()->json('data.projects'))->firstWhere('id', $ids[0])['readOnly'] ?? null);

        $this->deleteJson('/api/parts/'.$part.'?version=1&changeId='.Str::uuid())->assertForbidden()->assertJsonPath('code', 'project_read_only');
    }

    public function test_a_failed_payment_is_counted_for_the_card_testing_alert(): void
    {
        $user = $this->member();
        $this->subscribe($user);

        $this->stripeEvent('invoice.payment_failed', ['id' => 'in_1', 'customer' => $this->fresh($user)->stripe_id])->assertOk();

        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::PaymentFailed)->count());
    }
}
