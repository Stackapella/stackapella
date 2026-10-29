<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Mail\Account\PaymentFailed;
use App\Mail\Account\TrialEnding;
use App\Models\BillingDispute;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Support\Flags;
use App\Support\Trust\TrustLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Plus;
use Tests\TestCase;

final class BillingTest extends TestCase
{
    use Plus;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStripe();
        Mail::fake();
    }

    public function test_the_prices_and_the_trial_are_public(): void
    {
        $prices = $this->getJson('/api/billing/prices');

        $this->assertMatchesContract($prices, 'billing.prices', 200);
        $prices->assertJsonPath('data.trial', ['days' => 7, 'intervals' => ['year']])->assertJsonPath('data.prices.0', ['interval' => 'year', 'currency' => 'usd', 'amountCents' => 3999, 'taxInclusive' => false]);
    }

    public function test_checkout_starts_for_a_verified_account_and_remembers_the_moment(): void
    {
        $this->actingAs($this->member(verified: false));
        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => true, 'moment' => 'plus_song']), 'billing.checkout', 403);

        $user = $this->member();
        $this->actingAs($user);
        $none = $this->getJson('/api/billing');
        $this->assertMatchesContract($none, 'billing.show', 200);
        $none->assertJsonPath('data.status', 'none')->assertJsonPath('data.plan', 'free')->assertJsonPath('data.plusOfflineUntil', null);

        $checkout = $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'eur', 'trial' => true, 'moment' => 'plus_song']);
        $this->assertMatchesContract($checkout, 'billing.checkout', 201);
        $this->assertStringStartsWith('https://checkout.stripe.com/', (string) $checkout->json('data.url'));
        $this->assertSame(7, $this->stripe->checkouts[0]['trialDays']);
        $this->assertTrue($this->stripe->checkouts[0]['collectCard'], 'The yearly trial takes a card up front.');
        $this->assertStringContainsString('/plus/return?session={CHECKOUT_SESSION_ID}', $this->stripe->checkouts[0]['successUrl']);
        $this->assertSame('plus_song', CheckoutSession::query()->value('moment'));
        // A Plus course's buy-Plus moment is one of ours too.
        $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'eur', 'trial' => true, 'moment' => 'plus_course'])->assertCreated();
        $this->assertSame('plus_course', CheckoutSession::query()->latest('id')->value('moment'));

        // Until the webhook comes: pending, for 30 minutes.
        $this->getJson('/api/billing')->assertJsonPath('data.status', 'pending');
        $this->travel(31)->minutes();
        $this->getJson('/api/billing')->assertJsonPath('data.status', 'none');

        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'month', 'currency' => 'usd', 'trial' => true, 'moment' => 'x']), 'billing.checkout', 409);
        $this->postJson('/api/billing/checkout', ['interval' => 'month', 'currency' => 'usd', 'trial' => false, 'moment' => 'something-new'])->assertCreated();
        $this->assertSame('other', CheckoutSession::query()->latest('id')->value('moment'));
    }

    public function test_a_trial_becomes_plus_and_a_second_trial_on_the_same_card_is_refused(): void
    {
        $user = $this->member();
        $this->actingAs($user);
        $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => true, 'moment' => 'settings'])->assertCreated();
        $this->subscribe($user->refresh(), 'trialing');
        $this->actingAs($this->fresh($user));

        $plus = $this->getJson('/api/billing');
        $plus->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.status', 'trialing')->assertJsonPath('data.subscription.amountCents', 3999)->assertJsonPath('data.trial', null);
        $this->assertNotNull($plus->json('data.plusOfflineUntil'));
        $this->assertNotNull($user->refresh()->trial_used_at);

        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings']), 'billing.checkout', 409);

        // Someone else, with the same card.
        $other = $this->member();
        $this->actingAs($other);
        $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => true, 'moment' => 'settings'])->assertCreated();
        $this->subscribe($other->refresh(), 'trialing');

        $this->assertSame(['sub_'.$other->id], $this->stripe->cancelled);
        $this->stripeEvent('customer.subscription.deleted', $this->subscription($other->refresh(), 'canceled'))->assertOk();
        $this->actingAs($this->fresh($other));
        $this->getJson('/api/billing')->assertJsonPath('data.notice', 'card_had_trial')->assertJsonPath('data.plan', 'free')->assertJsonPath('data.status', 'none');
    }

    public function test_a_failed_payment_keeps_plus_while_stripe_retries_then_ends_it(): void
    {
        $user = $this->member();
        $this->actingAs($user);
        $this->subscribe($user);

        $this->stripeEvent('invoice.payment_failed', ['customer' => $user->refresh()->stripe_id, 'next_payment_attempt' => now()->addDays(3)->getTimestamp()])->assertOk();
        $this->stripeEvent('customer.subscription.updated', $this->subscription($user, 'past_due'))->assertOk();
        $this->actingAs($this->fresh($user));

        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.status', 'past_due')->assertJsonPath('data.notice', 'payment_failed');
        Mail::assertQueued(PaymentFailed::class);

        $this->stripeEvent('customer.subscription.deleted', $this->subscription($user, 'canceled', ['cancellation_details' => ['reason' => 'payment_failed']]))->assertOk();
        $this->actingAs($this->fresh($user));
        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'free')->assertJsonPath('data.status', 'ended')->assertJsonPath('data.endedReason', 'payment_failed');
    }

    public function test_the_trial_ending_email_and_a_paid_invoice(): void
    {
        $user = $this->member();
        $this->subscribe($user, 'trialing');

        $this->stripeEvent('customer.subscription.trial_will_end', $this->subscription($user->refresh(), 'trialing'))->assertOk();
        Mail::assertQueued(TrialEnding::class, fn (TrialEnding $mail): bool => $mail->hasTo((string) $user->email));

        $this->stripeEvent('invoice.paid', ['customer' => $user->stripe_id, 'amount_paid' => 3999])->assertOk();
        $this->assertSame(TrustLevel::TRUSTED, app(TrustLevel::class)->of($user->refresh()));
    }

    public function test_a_chargeback_ends_plus_drops_trust_and_shows_in_admin(): void
    {
        $user = $this->member();
        $this->subscribe($user);

        // As Stripe sends it: the dispute names its charge, not the customer.
        $this->stripe->charges['ch_1'] = (string) $user->refresh()->stripe_id;
        $this->stripeEvent('charge.dispute.created', ['id' => 'dp_1', 'charge' => 'ch_1', 'amount' => 3999, 'currency' => 'usd', 'reason' => 'fraudulent', 'status' => 'needs_response'])->assertOk();
        $this->stripeEvent('charge.dispute.updated', ['id' => 'dp_1', 'charge' => 'ch_1', 'amount' => 3999, 'currency' => 'usd', 'reason' => 'fraudulent', 'status' => 'under_review'])->assertOk();

        $user->refresh();
        $this->assertSame(0, app(TrustLevel::class)->of($user));
        $this->assertSame(['sub_'.$user->id], $this->stripe->cancelled, 'Cancelled once, not again on the update.');
        $this->assertSame('dispute', $user->plus_ended_reason);

        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/billing/disputes');
        $this->assertMatchesContract($list, 'admin.billing.disputes.list', 200);
        $list->assertJsonPath('data.0.reason', 'fraudulent')->assertJsonPath('data.0.person.id', $user->sqid());
        $this->assertSame(1, BillingDispute::query()->count());
    }

    public function test_the_portal_needs_a_customer_and_checkout_can_be_paused(): void
    {
        $user = $this->member();
        $this->actingAs($user);
        $this->assertMatchesContract($this->postJson('/api/billing/portal'), 'billing.portal', 409);

        $user->forceFill(['stripe_id' => 'cus_1'])->save();
        $this->assertMatchesContract($this->postJson('/api/billing/portal'), 'billing.portal', 201);

        app(Flags::class)->set('pause_checkout', 'on', null, 'Card testing');
        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings']), 'billing.checkout', 423);
    }

    public function test_in_the_eu_and_uk_plus_starts_at_once_only_with_consent_and_checkout_says_how_it_renews(): void
    {
        $this->getJson('/api/billing/prices')->assertJsonPath('data.startNowConsent', false);
        $this->withHeaders(['CF-IPCountry' => 'DE'])->getJson('/api/billing/prices')->assertJsonPath('data.startNowConsent', true);
        $this->withHeaders(['CF-IPCountry' => 'GB'])->getJson('/api/billing/prices')->assertJsonPath('data.startNowConsent', true);

        $this->actingAs($this->member());
        $refused = $this->withHeaders(['CF-IPCountry' => 'FR'])->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'eur', 'trial' => true, 'moment' => 'settings']);
        $this->assertMatchesContract($refused, 'billing.checkout', 422);
        $refused->assertJsonValidationErrors('startNowConsent');
        $this->assertSame([], $this->stripe->checkouts);

        $this->withHeaders(['CF-IPCountry' => 'FR'])->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'eur', 'trial' => true, 'moment' => 'settings', 'startNowConsent' => true])->assertCreated();
        $record = CheckoutSession::query()->sole();
        $this->assertSame(['FR', 1], [$record->country, $record->start_now_consent_version]);
        $this->assertNotNull($record->start_now_consent_at);
        // Stripe's page: the trial's end, then the renewal at its price, and how to cancel.
        $this->assertStringContainsString('Your free trial ends on '.now()->addDays(7)->isoFormat('LL'), $this->stripe->checkouts[0]['terms']);
        $this->assertStringContainsString('renews every year at €39.99 until you cancel', $this->stripe->checkouts[0]['terms']);

        // Elsewhere, nothing to consent to (and none recorded).
        $this->travel(31)->minutes();
        $this->withHeaders(['CF-IPCountry' => 'US'])->postJson('/api/billing/checkout', ['interval' => 'month', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings'])->assertCreated();
        $this->assertNull(CheckoutSession::query()->latest('id')->value('start_now_consent_at'));
        $this->assertStringStartsWith('Plus renews every month at $4.99 plus tax until', $this->stripe->checkouts[1]['terms']);
    }

    public function test_the_monthly_trial_takes_no_card_and_says_plus_ends_without_one(): void
    {
        config(['billing.no_card_trial_level' => TrustLevel::VERIFIED]);
        $this->actingAs($this->member());
        $this->withHeaders(['CF-IPCountry' => 'US'])->postJson('/api/billing/checkout', ['interval' => 'month', 'currency' => 'usd', 'trial' => true, 'moment' => 'settings'])->assertCreated();

        $this->assertFalse($this->stripe->checkouts[0]['collectCard']);
        $this->assertStringContainsString('Plus ends with it unless you add a card', $this->stripe->checkouts[0]['terms']);
        $this->assertStringContainsString('every month at $4.99 plus tax until you cancel', $this->stripe->checkouts[0]['terms']);
    }

    public function test_the_checkout_limit_and_validation(): void
    {
        $this->actingAs($this->member());
        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'week', 'currency' => 'yen', 'trial' => true]), 'billing.checkout', 422);

        config(['limits.checkout.account.max' => 2]);
        $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings'])->assertCreated();
        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings']), 'billing.checkout', 429);
    }

    public function test_the_return_page_works_signed_out(): void
    {
        $user = $this->member();
        $this->actingAs($user);
        $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('/plus/return?session=cs_test_1')->assertOk()->assertSee(__('billing.return.waiting_title'));
        $this->subscribe(User::query()->findOrFail($user->id));
        $this->get('/plus/return?session=cs_test_1')->assertOk()->assertSee(__('billing.return.done_title'));
    }

    public function test_checkout_is_off_until_stripe_is_set_up(): void
    {
        config(['cashier.secret' => '']);
        $this->actingAs($this->member());

        $this->assertMatchesContract($this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false, 'moment' => 'settings']), 'billing.checkout', 423);
        $this->assertMatchesContract($this->postJson('/api/billing/portal'), 'billing.portal', 423);
        $this->getJson('/api/billing')->assertOk();
    }

    public function test_a_refund_from_admin_ends_plus_at_once_even_a_part_one(): void
    {
        $person = User::factory()->create();
        $this->subscribe($person);
        $this->assertTrue($person->refresh()->subscribed('default'));
        $this->stripe->latest = ['paymentIntent' => 'pi_plus', 'cents' => 3999, 'refunded' => 0, 'currency' => 'gbp', 'paidAt' => now()->getTimestamp()];

        $this->actingAsAdmin();
        $this->postJson('/api/admin/people/'.$person->sqid().'/refund', ['amountCents' => 1000, 'note' => 'Half the year, as agreed.'])->assertOk();

        $this->assertSame(['sub_'.$person->id], $this->stripe->cancelled);
        $this->assertSame('refunded', $person->refresh()->plus_ended_reason);
        $this->assertFalse($person->subscribed('default'));
        $this->getJson('/api/admin/people/'.$person->sqid())->assertJsonPath('data.plan.plus', false);
    }
}
