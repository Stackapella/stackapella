<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Models\AuditLog;
use App\Models\CheckoutSession;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Plus;
use Tests\TestCase;

/**
 * The billing review (2026-11-21) and Hillel's "no free trial": Checkout starts paid, a refund in Stripe ends Plus,
 * two Checkouts can't both be paid, a payment past its last retry stops Plus, and the EU consent is asked by the
 * account's country too and kept on every receipt.
 */
final class BillingReviewTest extends TestCase
{
    use Plus;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeStripe();
        Mail::fake();
        // As live: no trial.
        config(['billing.trial_days' => 0]);
    }

    public function test_with_no_trial_checkout_starts_paid_even_when_an_older_app_asks_for_one(): void
    {
        $this->actingAs($this->member());

        $this->getJson('/api/billing')->assertJsonPath('data.trial', null);
        $this->getJson('/api/billing/prices')->assertJsonPath('data.trial', null);
        $this->postJson('/api/billing/checkout', ['interval' => 'month', 'currency' => 'usd', 'trial' => true, 'moment' => 'settings'])->assertCreated();

        $this->assertNull($this->stripe->checkouts[0]['trialDays']);
        $this->assertTrue($this->stripe->checkouts[0]['collectCard']);
        $this->assertFalse((bool) CheckoutSession::query()->firstOrFail()->trial);
    }

    public function test_someone_already_in_a_trial_keeps_it(): void
    {
        $ada = $this->member();
        $this->subscribe($ada, 'trialing');

        $this->assertTrue(app(Plans::class)->isPlus($this->fresh($ada)));
        $this->actingAs($this->fresh($ada))->getJson('/api/billing')->assertJsonPath('data.status', 'trialing')->assertJsonPath('data.plan', 'plus');
    }

    public function test_a_full_refund_in_stripes_dashboard_ends_plus(): void
    {
        $ada = $this->member();
        $this->subscribe($ada);
        $this->stripe->latest = ['paymentIntent' => 'pi_plus', 'cents' => 3999, 'refunded' => 0, 'currency' => 'usd', 'paidAt' => now()->getTimestamp()];

        // A partial refund, or another payment's: Plus stays.
        $this->stripeEvent('charge.refunded', ['id' => 'ch_1', 'customer' => $this->fresh($ada)->stripe_id, 'payment_intent' => 'pi_plus', 'refunded' => false, 'amount_refunded' => 1000])->assertOk();
        $this->stripeEvent('charge.refunded', ['id' => 'ch_2', 'customer' => $this->fresh($ada)->stripe_id, 'payment_intent' => 'pi_coaching', 'refunded' => true, 'amount_refunded' => 5000])->assertOk();
        $this->assertTrue(app(Plans::class)->isPlus($this->fresh($ada)));

        $this->stripeEvent('charge.refunded', ['id' => 'ch_1', 'customer' => $this->fresh($ada)->stripe_id, 'payment_intent' => 'pi_plus', 'refunded' => true, 'amount_refunded' => 3999])->assertOk();

        $this->assertFalse(app(Plans::class)->isPlus($this->fresh($ada)));
        $this->assertSame('refunded', $this->fresh($ada)->plus_ended_reason);
        $this->assertSame(['sub_'.$ada->id], $this->stripe->cancelled);
    }

    public function test_a_second_checkout_closes_the_first_and_one_just_paid_waits(): void
    {
        $this->actingAs($this->member());
        $body = ['interval' => 'year', 'currency' => 'usd', 'moment' => 'settings'];

        $this->postJson('/api/billing/checkout', $body)->assertCreated();
        $this->postJson('/api/billing/checkout', $body)->assertCreated();
        $this->assertSame(['cs_test_1'], $this->stripe->closed);

        // The second was paid a moment ago: a third waits for its webhook.
        $this->stripe->sessions['cs_test_2'] = 'complete';
        $this->assertMatchesContract($this->postJson('/api/billing/checkout', $body), 'billing.checkout', 409);
    }

    public function test_a_second_plus_subscription_is_cancelled_and_refunded_and_the_first_kept(): void
    {
        $ada = $this->member();
        $this->subscribe($ada);

        $this->stripeEvent('customer.subscription.created', $this->subscription($this->fresh($ada), 'active', ['id' => 'sub_second', 'items' => ['data' => [['id' => 'si_second', 'quantity' => 1, 'price' => ['id' => 'price_year', 'product' => 'prod_plus']]]]]))->assertOk();

        $this->assertSame(['sub_second'], $this->stripe->cancelled);
        $this->assertSame([['sub_second', 'second-plus:sub_second']], $this->stripe->subscriptionRefunds);
        $this->assertSame(['sub_'.$ada->id], $this->fresh($ada)->subscriptions()->pluck('stripe_id')->all());
        $this->assertTrue(app(Plans::class)->isPlus($this->fresh($ada)));
        $this->assertTrue(AuditLog::query()->where('action', 'billing.second_plus_refunded')->exists());
    }

    public function test_plus_stops_three_days_after_a_failed_payments_last_retry(): void
    {
        $ada = $this->member();
        $this->subscribe($ada);
        $this->fresh($ada)->subscriptions()->update(['stripe_status' => 'past_due']);
        $this->fresh($ada)->forceFill(['past_due_until' => now()->subDays(2)])->save();

        $this->assertTrue(app(Plans::class)->isPlus($this->fresh($ada)), 'Within the grace.');

        $this->fresh($ada)->forceFill(['past_due_until' => now()->subDays(4)])->save();
        $this->assertFalse(app(Plans::class)->isPlus($this->fresh($ada)));
    }

    public function test_the_eu_consent_is_asked_by_the_accounts_country_and_kept_on_every_receipt(): void
    {
        $ada = $this->member();
        $ada->forceFill(['country' => 'DE'])->save();
        $this->actingAs($this->fresh($ada));

        // A US network, but the account's latest country is Germany.
        $this->withHeader('CF-IPCountry', 'US')->getJson('/api/billing/prices')->assertJsonPath('data.startNowConsent', true);
        $this->withHeader('CF-IPCountry', 'US')->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'eur', 'moment' => 'settings'])->assertStatus(422);
        $this->withHeader('CF-IPCountry', 'US')->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'eur', 'moment' => 'settings', 'startNowConsent' => true])->assertCreated();

        $this->assertStringContainsString('14-day right to cancel', (string) $this->stripe->checkouts[0]['receiptNote']);
    }

    public function test_billed_in_the_eu_without_the_consent_keeps_the_right_to_withdraw_on_record(): void
    {
        $ada = $this->member();
        $this->actingAs($ada)->withHeader('CF-IPCountry', 'US')->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'moment' => 'settings'])->assertCreated();
        $this->assertNull($this->stripe->checkouts[0]['receiptNote']);

        $this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_1', 'customer' => $this->fresh($ada)->stripe_id, 'customer_details' => ['address' => ['country' => 'fr']]])->assertOk();

        $this->assertSame('FR', AuditLog::query()->where('action', 'billing.withdrawal_right_kept')->firstOrFail()->after['country'] ?? null);
    }
}
