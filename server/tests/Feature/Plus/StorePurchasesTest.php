<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Actions\Billing\StoreSubscriptions;
use App\Models\StoreSubscription;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Plus;
use Tests\Support\StoreFakes;
use Tests\TestCase;

/**
 * Plus bought in the apps (docs/app-purchases-plan.md): App Store and Google Play subscriptions,
 * linked to one account, kept current by the stores' notifications, one Plus whatever sold it.
 */
final class StorePurchasesTest extends TestCase
{
    use Plus;
    use RefreshDatabase;
    use StoreFakes;

    public function test_an_app_store_purchase_gives_the_account_plus_and_is_never_moved_to_another(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $this->actingAs($ada);
        $this->assertNotNull($ada->store_account_token);
        $this->getJson('/api/billing')->assertJsonPath('data.storeAccountToken', $ada->store_account_token)->assertJsonPath('data.source', null);

        $bought = $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000001', ['appAccountToken' => $ada->store_account_token])]]);
        $this->assertMatchesContract($bought, 'billing.store.sync', 200);
        $bought->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.source', 'apple')->assertJsonPath('data.store.interval', 'year')->assertJsonPath('data.store.willRenew', true);
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
        // Checkout isn't offered twice.
        $this->fakeStripe();
        $this->postJson('/api/billing/checkout', ['interval' => 'year', 'currency' => 'usd', 'trial' => false])->assertStatus(409)->assertJsonPath('code', 'already_plus');

        // Restoring on another account: the purchase stays Ada's.
        $bo = $this->member();
        $this->actingAs($bo);
        $elsewhere = $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000001')]]);
        $this->assertMatchesContract($elsewhere, 'billing.store.sync', 409);
        $elsewhere->assertJsonPath('billing.plan', 'free');
        // Made with Ada's token, though never linked: hers too.
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000002', ['appAccountToken' => $ada->store_account_token])]])->assertStatus(409);
        $this->assertFalse(app(Plans::class)->isPlus($bo->refresh()));
    }

    public function test_only_apples_signature_and_our_products_count(): void
    {
        $this->actingAs($this->member());
        $this->assertMatchesContract($this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => ['x']]), 'billing.store.sync', 423);

        $this->fakeAppStore();
        $real = $this->appleTransaction('2000000003');
        // Signed by a chain that isn't Apple's (the pinned root differs).
        $theirs = $this->appleTransaction('2000000003');
        config(['services.app_store.root_certificate' => resource_path('certs/AppleRootCA-G3.pem')]);
        $this->assertMatchesContract($this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$theirs]]), 'billing.store.sync', 422);
        $this->fakeAppStore();

        // Tampered with after signing.
        [$head, $body, $signature] = explode('.', $real);
        $forged = $head.'.'.rtrim(strtr(base64_encode((string) json_encode([...json_decode((string) base64_decode(strtr($body, '-_', '+/')), true), 'expiresDate' => now()->addYears(9)->getTimestampMs()])), '+/', '-_'), '=').'.'.$signature;
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$forged]])->assertStatus(422)->assertJsonPath('code', 'invalid_purchase');
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000004', ['bundleId' => 'com.someone.else'])]])->assertStatus(422);
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000004', ['productId' => 'com.stackapella.coins'])]])->assertStatus(422);
        $this->assertMatchesContract($this->postJson('/api/billing/store', ['store' => 'apple']), 'billing.store.sync', 422);
        $this->assertSame(0, StoreSubscription::query()->count());
    }

    public function test_apples_notifications_renew_hold_end_and_refund_plus(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $this->actingAs($ada)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000010', ['expiresDate' => now()->addDay()->getTimestampMs()])]])->assertOk();
        $transaction = ['originalTransactionId' => '2000000010'];

        // A renewal carries the new end.
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('DID_RENEW', [...$transaction, 'expiresDate' => now()->addYear()->getTimestampMs()])])->assertOk();
        $this->assertTrue($ada->refresh()->store_plus_until?->gt(now()->addMonths(11)));

        // A failed renewal in grace keeps Plus; past it, Plus ends.
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('DID_FAIL_TO_RENEW', [...$transaction, 'expiresDate' => now()->subMinute()->getTimestampMs()], 'GRACE_PERIOD', ['autoRenewStatus' => 1, 'gracePeriodExpiresDate' => now()->addDays(16)->getTimestampMs()])])->assertOk();
        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.store.inGracePeriod', true);
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('GRACE_PERIOD_EXPIRED', [...$transaction, 'expiresDate' => now()->subDay()->getTimestampMs()])])->assertOk();
        $this->assertFalse(app(Plans::class)->isPlus($ada->refresh()));

        // Back on, then refunded: off again, and an older notification never undoes it.
        $this->travel(1)->minutes();
        $old = $this->appleNotification('DID_RENEW', [...$transaction, 'expiresDate' => now()->addYear()->getTimestampMs()]);
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('DID_RENEW', [...$transaction, 'expiresDate' => now()->addYear()->getTimestampMs()])])->assertOk();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('REFUND', [...$transaction, 'revocationDate' => now()->getTimestampMs()])])->assertOk();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $old])->assertOk();
        $this->assertSame(StoreSubscription::REVOKED, StoreSubscription::query()->sole()->status);
        $this->assertFalse(app(Plans::class)->isPlus($ada->refresh()));

        // Not Apple's: refused, and nothing changes.
        $this->postJson('/webhooks/app-store', ['signedPayload' => 'a.b.c'])->assertStatus(400);
        $this->postJson('/webhooks/app-store', [])->assertStatus(400);
    }

    public function test_a_purchase_apple_tells_us_of_first_finds_its_account_by_token(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();

        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('SUBSCRIBED', ['originalTransactionId' => '2000000020', 'appAccountToken' => $ada->store_account_token])])->assertOk();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
        $this->assertSame($ada->id, StoreSubscription::query()->sole()->user_id);
    }

    public function test_a_google_play_purchase_is_checked_acknowledged_and_kept_current_by_its_notifications(): void
    {
        $ada = $this->member();
        $this->actingAs($ada);
        $this->assertMatchesContract($this->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-1']]]), 'billing.store.sync', 423);

        $this->fakePlay();
        $this->playPurchase('tok-1', $ada->store_account_token);
        $bought = $this->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-1']]]);
        $this->assertMatchesContract($bought, 'billing.store.sync', 200);
        $bought->assertJsonPath('data.source', 'google')->assertJsonPath('data.store.interval', 'month');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'purchases/subscriptions/plus/tokens/tok-1:acknowledge'));
        $this->assertNotNull(StoreSubscription::query()->sole()->acknowledged_at);

        // Another account's token, or one Google doesn't know.
        $this->playPurchase('tok-2', 'someone-else');
        $this->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-2']]])->assertStatus(409)->assertJsonPath('code', 'store_linked_elsewhere');
        $this->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'nope']]])->assertStatus(422)->assertJsonPath('code', 'invalid_purchase');

        // On hold after a failed renewal: Plus ends; then a refund.
        $this->playPurchase('tok-1', $ada->store_account_token, ['subscriptionState' => 'SUBSCRIPTION_STATE_ON_HOLD', 'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED']);
        [$body, $headers] = $this->playPush(['subscriptionNotification' => ['notificationType' => 5, 'purchaseToken' => 'tok-1', 'subscriptionId' => 'plus']]);
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertNoContent();
        $this->assertFalse(app(Plans::class)->isPlus($ada->refresh()));

        $this->playPurchase('tok-1', $ada->store_account_token, ['acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED']);
        [$body, $headers] = $this->playPush(['subscriptionNotification' => ['notificationType' => 2, 'purchaseToken' => 'tok-1', 'subscriptionId' => 'plus']]);
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertNoContent();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));

        // A refund of an earlier order, with the subscription kept running: Plus stays.
        [$body, $headers] = $this->playPush(['voidedPurchaseNotification' => ['purchaseToken' => 'tok-1', 'orderId' => 'GPA.1', 'productType' => 1]]);
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertNoContent();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));

        // Refunded and revoked: Plus ends.
        $this->playPurchase('tok-1', $ada->store_account_token, ['subscriptionState' => 'SUBSCRIPTION_STATE_EXPIRED', 'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED']);
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertNoContent();
        $this->assertFalse(app(Plans::class)->isPlus($ada->refresh()));

        // A push that isn't Google's, for us, is refused.
        [$body, $headers] = $this->playPush(['subscriptionNotification' => ['notificationType' => 2, 'purchaseToken' => 'tok-1']], audience: 'https://elsewhere.example');
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertUnauthorized();
        $this->postJson('/webhooks/google-play', $body)->assertUnauthorized();
    }

    public function test_an_old_copy_of_a_transaction_never_undoes_a_refund_or_a_newer_event(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $saved = $this->appleTransaction('2000000030');
        $this->actingAs($ada)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$saved]])->assertOk();

        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('REFUND', ['originalTransactionId' => '2000000030', 'revocationDate' => now()->getTimestampMs()])])->assertOk();
        $this->assertFalse(app(Plans::class)->isPlus($ada->refresh()));

        // The copy kept from before the refund, sent again: still refunded.
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$saved]])->assertOk()->assertJsonPath('data.plan', 'free');
        $this->assertSame(StoreSubscription::REVOKED, StoreSubscription::query()->sole()->status);
    }

    public function test_a_restore_links_what_it_can_and_a_resubscribe_on_another_account_moves_there(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $this->actingAs($ada);

        // One of ours and one that isn't: ours is linked, and Plus counts.
        $restored = $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000040'), $this->appleTransaction('2000000041', ['productId' => 'com.stackapella.coins'])]]);
        $restored->assertOk()->assertJsonPath('data.plan', 'plus');

        // The same Apple ID subscribes again from Bo's account, its transaction signed with his
        // token: it's his now, and Ada's Plus from it ends.
        $bo = $this->member();
        $this->actingAs($bo);
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000040', ['appAccountToken' => $bo->store_account_token])]])->assertOk()->assertJsonPath('data.plan', 'plus');
        $this->assertSame($bo->id, StoreSubscription::query()->where('original_id', '2000000040')->value('user_id'));
        $this->assertFalse(app(Plans::class)->isPlus($ada->refresh()));
    }

    public function test_a_plan_change_on_google_play_is_acknowledged_and_a_late_event_for_the_old_token_changes_nothing(): void
    {
        $this->fakePlay();
        $ada = $this->member();
        $this->actingAs($ada);
        $this->playPurchase('tok-a', $ada->store_account_token);
        $this->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-a']]])->assertOk();

        // Monthly to yearly: a new token, linked to the old one, waiting for its own acknowledgement.
        $this->playPurchase('tok-b', $ada->store_account_token, ['linkedPurchaseToken' => 'tok-a', 'lineItems' => [['productId' => 'plus', 'expiryTime' => now()->addYear()->utc()->toIso8601ZuluString(), 'autoRenewingPlan' => ['autoRenewEnabled' => true], 'offerDetails' => ['basePlanId' => 'year']]]]);
        $this->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-b']]])->assertOk()->assertJsonPath('data.store.interval', 'year');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'tokens/tok-b:acknowledge'));
        $this->assertSame(1, StoreSubscription::query()->count());

        // The old token's late "expired": nothing changes.
        $this->playPurchase('tok-a', $ada->store_account_token, ['subscriptionState' => 'SUBSCRIPTION_STATE_EXPIRED', 'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED']);
        [$body, $headers] = $this->playPush(['subscriptionNotification' => ['notificationType' => 13, 'purchaseToken' => 'tok-a', 'subscriptionId' => 'plus']]);
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertNoContent();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
        $this->assertSame(hash('sha256', 'tok-b'), StoreSubscription::query()->sole()->token_hash);
    }

    public function test_the_hourly_sweep_acknowledges_what_a_failed_call_left(): void
    {
        $this->fakePlay();
        $row = StoreSubscription::factory()->google()->create(['acknowledged_at' => null]);
        $this->playPurchase((string) $row->purchase_token, $row->user?->store_account_token);

        $this->artisan('app:store:acknowledge')->assertSuccessful();
        $this->assertNotNull($row->refresh()->acknowledged_at);
    }

    public function test_apple_sync_needs_apples_server_api_and_waits_when_apple_is_down(): void
    {
        $this->fakeAppStore();
        $this->actingAs($this->member());
        $transaction = $this->appleTransaction('2000000050');

        // Sign in with Apple's bundle id alone doesn't turn it on: the Server API key does.
        config(['services.app_store.private_key' => null]);
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$transaction]])->assertStatus(423);
        $this->fakeAppStore();

        $this->appleDown = true;
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$transaction]])->assertStatus(503)->assertJsonPath('code', 'unavailable');
        $this->assertNull(StoreSubscription::query()->first()?->user_id);

        // A transaction Apple doesn't know is refused.
        $this->appleDown = false;
        $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleSigned(['originalTransactionId' => '2000000059', 'bundleId' => 'com.stackapella.app', 'productId' => 'com.stackapella.plus.year', 'signedDate' => now()->getTimestampMs()])]])->assertStatus(422)->assertJsonPath('code', 'invalid_purchase');
    }

    public function test_sandbox_purchases_give_plus_only_to_store_testers(): void
    {
        $this->fakeAppStore();
        $tester = $this->member();
        $this->actingAs($tester)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000060', ['environment' => 'Sandbox'])]])->assertOk()->assertJsonPath('data.plan', 'free');

        $this->artisan('app:store:tester', ['email' => $tester->email])->assertSuccessful();
        $this->assertTrue(app(Plans::class)->isPlus($tester->refresh()));
        $this->artisan('app:store:tester', ['email' => $tester->email, '--off' => true])->assertSuccessful();
        $this->assertFalse(app(Plans::class)->isPlus($tester->refresh()));
    }

    public function test_admin_marks_store_testers_and_lists_them(): void
    {
        $this->fakeAppStore();
        $tester = $this->member();
        $this->actingAs($tester)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000070', ['environment' => 'Sandbox'])]])->assertOk()->assertJsonPath('data.plan', 'free');

        $this->actingAs($tester)->putJson('/api/admin/people/'.$tester->sqid().'/store-tester', ['tester' => true])->assertForbidden();
        $this->actingAsAdmin();
        $marked = $this->putJson('/api/admin/people/'.$tester->sqid().'/store-tester', ['tester' => true, 'note' => 'App Review']);
        $this->assertMatchesContract($marked, 'admin.people.storeTester.update', 200);
        $this->assertNotNull($marked->json('data.storeTesterSince'));
        $this->assertTrue(app(Plans::class)->isPlus($tester->refresh()));
        $this->assertMatchesContract($this->putJson('/api/admin/people/'.$tester->sqid().'/store-tester', []), 'admin.people.storeTester.update', 422);

        $list = $this->getJson('/api/admin/store-testers');
        $this->assertMatchesContract($list, 'admin.storeTesters.list', 200);
        $list->assertJsonPath('data.0.id', $tester->sqid());

        $this->putJson('/api/admin/people/'.$tester->sqid().'/store-tester', ['tester' => false])->assertOk()->assertJsonPath('data.storeTesterSince', null);
        $this->assertFalse(app(Plans::class)->isPlus($tester->refresh()));
    }

    public function test_an_account_made_without_its_store_token_gets_one(): void
    {
        $user = $this->member();
        $user->forceFill(['store_account_token' => null])->save();

        $this->artisan('app:store:tokens')->expectsOutputToContain('Gave 1 accounts')->assertSuccessful();
        $this->assertNotNull($user->refresh()->store_account_token);
    }

    public function test_paying_twice_is_said_and_plus_stays_one(): void
    {
        $this->fakeStripe();
        $ada = $this->member();
        $this->subscribe($ada);
        StoreSubscription::factory()->create(['user_id' => $ada->id]);
        app(StoreSubscriptions::class)->recount($ada);

        $this->actingAs($ada->refresh())->getJson('/api/billing')->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.source', 'stripe')->assertJsonPath('data.notice', 'plus_twice');
    }

    public function test_a_refund_of_an_old_renewal_leaves_the_period_being_paid(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $this->actingAs($ada)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000080', ['appAccountToken' => $ada->store_account_token])]])->assertOk();
        $current = $this->appleLatest['2000000080'];
        $old = ['originalTransactionId' => '2000000080', 'transactionId' => '20000000800', 'expiresDate' => now()->subYear()->getTimestampMs(), 'revocationDate' => now()->getTimestampMs()];

        // Apple's current transaction is still the one being paid: the refund names last year's.
        $this->travel(1)->minutes();
        $refund = $this->appleNotification('REFUND', $old);
        $this->appleLatest['2000000080'] = $current;
        $this->postJson('/webhooks/app-store', ['signedPayload' => $refund])->assertOk();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
        $this->assertNull(StoreSubscription::query()->sole()->refunded_at);

        // Without the Server API, a transaction from an earlier period changes nothing either.
        config(['services.app_store.private_key' => null]);
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('REFUND', $old)])->assertOk();
        $this->assertSame(StoreSubscription::ACTIVE, StoreSubscription::query()->sole()->status);
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
    }

    public function test_plus_from_a_store_ending_is_recorded_as_stripes_is(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $transaction = ['originalTransactionId' => '2000000090'];
        $this->actingAs($ada)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000090', ['expiresDate' => now()->addDay()->getTimestampMs()])]])
            ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.source', 'apple');

        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('EXPIRED', [...$transaction, 'expiresDate' => now()->subMinute()->getTimestampMs()])])->assertOk();
        $this->assertSame('canceled', $ada->refresh()->plus_ended_reason);
        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'free')->assertJsonPath('data.status', 'ended')->assertJsonPath('data.endedReason', 'canceled');

        // Subscribed again: the ending is cleared.
        $this->travel(1)->minutes();
        $this->postJson('/webhooks/app-store', ['signedPayload' => $this->appleNotification('DID_RENEW', [...$transaction, 'expiresDate' => now()->addYear()->getTimestampMs()])])->assertOk();
        $this->assertNull($ada->refresh()->plus_ended_at);
        $this->getJson('/api/billing')->assertJsonPath('data.status', 'active')->assertJsonPath('data.endedAt', null);
    }

    public function test_the_apps_copy_of_a_transaction_never_moves_a_subscription(): void
    {
        $this->fakeAppStore();
        $ada = $this->member();
        $this->actingAs($ada)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000100')]])->assertOk();
        $current = $this->appleLatest['2000000100'];

        // Bo's app sends a copy signed with his token, but Apple's current transaction names nobody.
        $bo = $this->member();
        $copy = $this->appleTransaction('2000000100', ['appAccountToken' => $bo->store_account_token]);
        $this->appleLatest['2000000100'] = $current;
        $this->actingAs($bo)->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$copy]])->assertStatus(409);
        $this->assertSame($ada->id, StoreSubscription::query()->sole()->user_id);
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
    }

    public function test_an_older_google_token_never_moves_or_ends_the_subscription(): void
    {
        $this->fakePlay();
        $ada = $this->member();
        $bo = $this->member();
        $this->playPurchase('tok-a', $bo->store_account_token);
        $this->actingAs($bo)->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-a']]])->assertOk();

        // The same Google account subscribes again from Ada's account: hers now.
        $this->playPurchase('tok-a', $bo->store_account_token, ['subscriptionState' => 'SUBSCRIPTION_STATE_EXPIRED']);
        $this->playPurchase('tok-b', $ada->store_account_token, ['linkedPurchaseToken' => 'tok-a']);
        $this->actingAs($ada)->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-b']]])->assertOk();
        $this->assertFalse(app(Plans::class)->isPlus($bo->refresh()));

        // Bo restores the token tok-b replaced: nothing moves back.
        $this->actingAs($bo)->postJson('/api/billing/store', ['store' => 'google', 'purchases' => [['productId' => 'plus', 'purchaseToken' => 'tok-a']]])->assertStatus(409);
        $this->assertSame($ada->id, StoreSubscription::query()->sole()->user_id);

        // Google voids the old token, which it no longer knows: the current one stands.
        unset($this->playSubscriptions['tok-a']);
        [$body, $headers] = $this->playPush(['voidedPurchaseNotification' => ['purchaseToken' => 'tok-a', 'orderId' => 'GPA.1', 'productType' => 1, 'refundType' => 1]]);
        $this->withHeaders($headers)->postJson('/webhooks/google-play', $body)->assertNoContent();
        $this->assertTrue(app(Plans::class)->isPlus($ada->refresh()));
    }

    public function test_the_store_syncs_limits_and_outages_are_in_the_contract(): void
    {
        $this->fakeAppStore();
        $this->actingAs($this->member());
        $this->appleDown = true;
        $this->assertMatchesContract($this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$this->appleTransaction('2000000110')]]), 'billing.store.sync', 503);

        $this->appleDown = false;
        $transaction = $this->appleTransaction('2000000110');

        for ($i = 0; $i < 29; $i++) {
            $this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$transaction]])->assertOk();
        }

        $this->assertMatchesContract($this->postJson('/api/billing/store', ['store' => 'apple', 'transactions' => [$transaction]]), 'billing.store.sync', 429);
    }

    public function test_web_plus_cancelled_with_a_store_renewing_comes_from_the_store(): void
    {
        $this->fakeStripe();
        $ada = $this->member();
        $this->subscribe($ada);
        $ada->subscription('default')?->forceFill(['ends_at' => now()->addDays(3)])->save();
        StoreSubscription::factory()->create(['user_id' => $ada->id]);
        app(StoreSubscriptions::class)->recount($ada);

        $billing = $this->actingAs($ada->refresh())->getJson('/api/billing')->assertJsonPath('data.source', 'apple');
        // Offline, Plus lasts its usual days: the store renews it.
        $this->assertTrue(now()->addDays(5)->lt((string) $billing->json('data.plusOfflineUntil')));
    }
}
