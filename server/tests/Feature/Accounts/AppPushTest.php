<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\AppDevice;
use App\Models\AppPush;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Accounts\AppTokens;
use App\Support\Gallery\Bell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Push to the native apps (contract/api.md, account.devices.push.*): a device registers its token,
 * and the bell's new items are pushed through APNs and FCM, as each person set them, around their
 * quiet hours.
 */
final class AppPushTest extends TestCase
{
    use RefreshDatabase;

    private const APNS_TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * An app device of theirs, signed in: its access token.
     */
    private function device(User $user, string $platform = 'ios'): string
    {
        return app(AppTokens::class)->issue($user, ['name' => 'Phone', 'platform' => $platform], 'password', '127.0.0.1')['accessToken'];
    }

    private function asApp(string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    }

    private function pushOn(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
        openssl_pkey_export($key, $apns);
        $rsa = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($rsa, $google);
        config([
            'services.app_push.enabled' => true,
            'services.apns' => ['key_id' => 'KEY1', 'team_id' => 'TEAM', 'private_key' => $apns, 'topic' => 'com.stackapella.app'],
            'services.fcm' => ['project_id' => 'stackapella', 'service_account' => json_encode(['client_email' => 'push@stackapella.iam.gserviceaccount.com', 'private_key' => $google])],
        ]);
    }

    public function test_an_app_registers_its_push_token_and_signing_out_clears_it(): void
    {
        $ada = User::factory()->create();
        $phone = $this->device($ada);

        $set = $this->asApp($phone)->putJson('/api/account/devices/current/push', ['platform' => 'apns', 'token' => self::APNS_TOKEN, 'environment' => 'sandbox']);
        $this->assertMatchesContract($set, 'account.devices.push.update', 200);
        $set->assertJsonPath('data.push', true)->assertJsonPath('data.current', true);
        $device = AppDevice::query()->sole();
        $this->assertSame([self::APNS_TOKEN, 'sandbox'], [$device->push_token, $device->push_environment]);
        $this->assertNotSame(self::APNS_TOKEN, $device->getRawOriginal('push_token'));
        $this->assertMatchesContract($this->asApp($phone)->putJson('/api/account/devices/current/push', ['platform' => 'apns', 'token' => 'short']), 'account.devices.push.update', 422);

        // A browser has no device to push to.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->assertMatchesContract($this->actingAs($ada)->putJson('/api/account/devices/current/push', ['platform' => 'fcm', 'token' => str_repeat('f', 40)]), 'account.devices.push.update', 403);
        $this->assertMatchesContract($this->actingAs($ada)->deleteJson('/api/account/devices/current/push'), 'account.devices.push.delete', 403);

        // The same token on a new install's device: taken off the old one.
        $again = $this->device($ada);
        $this->asApp($again)->putJson('/api/account/devices/current/push', ['platform' => 'apns', 'token' => self::APNS_TOKEN, 'environment' => 'sandbox'])->assertOk();
        $this->assertNull($device->refresh()->push_token_hash);
        $this->asApp($again)->getJson('/api/account/devices')->assertJsonPath('data.0.push', true);

        $this->assertMatchesContract($this->asApp($again)->deleteJson('/api/account/devices/current/push'), 'account.devices.push.delete', 204);
        $this->assertSame(0, AppDevice::query()->whereNotNull('push_token_hash')->count());

        $this->asApp($again)->putJson('/api/account/devices/current/push', ['platform' => 'fcm', 'token' => str_repeat('f', 40)])->assertOk();
        $this->asApp($again)->postJson('/api/auth/app/sign-out')->assertNoContent();
        $this->assertSame(0, AppDevice::query()->whereNotNull('push_token_hash')->count());
    }

    public function test_the_bell_is_pushed_to_each_device_as_its_person_set_it(): void
    {
        $this->pushOn();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.push', 'expires_in' => 3600]),
            'api.sandbox.push.apple.com/*' => Http::response([], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/stackapella/messages/1']),
        ]);
        $ada = User::factory()->create(['locale' => 'en']);
        $bo = User::factory()->create(['name' => 'Bo']);
        $this->asApp($this->device($ada))->putJson('/api/account/devices/current/push', ['platform' => 'apns', 'token' => self::APNS_TOKEN, 'environment' => 'sandbox'])->assertOk();
        $this->asApp($this->device($ada, 'android'))->putJson('/api/account/devices/current/push', ['platform' => 'fcm', 'token' => str_repeat('f', 40)])->assertOk();
        $harmony = Harmony::factory()->create(['user_id' => $ada->id, 'title' => 'Shenandoah']);

        Bell::tell($ada->id, 'comment', $bo, $harmony);
        // A day's likes on one harmony push once.
        Bell::tell($ada->id, 'like', $bo, $harmony);
        Bell::tell($ada->id, 'like', User::factory()->create(), $harmony);
        $this->assertSame(2, AppPush::query()->count());

        $this->artisan('app:push:send')->expectsOutputToContain('Sent 2')->assertSuccessful();
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'api.sandbox.push.apple.com/3/device/'.self::APNS_TOKEN)
            && $r->header('apns-topic') === ['com.stackapella.app'] && $r['aps']['alert']['body'] === 'Bo commented on “Shenandoah”.' && $r['url'] === '/h/'.$harmony->public_id);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/stackapella/messages:send')
            && $r['message']['data'] === ['type' => 'comment', 'url' => '/h/'.$harmony->public_id] && $r['message']['token'] === str_repeat('f', 40));
        $this->assertSame(0, AppPush::query()->whereNull('sent_at')->count());

        // Comments turned off: no push for the next one.
        $this->asApp($this->device($ada))->patchJson('/api/account', ['pushTypes' => ['comment' => false]])->assertOk()->assertJsonPath('data.pushTypes.comment', false)->assertJsonPath('data.pushTypes.like', true);
        Bell::tell($ada->id, 'comment', $bo, $harmony);
        $this->assertSame(2, AppPush::query()->count());
    }

    public function test_quiet_hours_hold_a_push_a_stale_one_is_dropped_and_a_gone_token_forgotten(): void
    {
        $this->pushOn();
        Http::fake(['api.push.apple.com/*' => Http::response(['reason' => 'Unregistered'], 410)]);
        $this->travelTo(now()->setTimezone('Europe/London')->setTime(23, 0)->utc());
        $ada = User::factory()->create(['timezone' => 'Europe/London']);
        $phone = $this->device($ada);
        $this->asApp($phone)->putJson('/api/account/devices/current/push', ['platform' => 'apns', 'token' => self::APNS_TOKEN, 'environment' => 'production'])->assertOk();
        $this->asApp($phone)->patchJson('/api/account', ['quietHours' => ['start' => '22:00', 'end' => '07:30']])->assertOk()->assertJsonPath('data.quietHours.end', '07:30');
        $harmony = Harmony::factory()->create(['user_id' => $ada->id]);

        Bell::tell($ada->id, 'featured', null, $harmony);
        $push = AppPush::query()->sole();
        $this->assertSame('07:30', $push->send_after->setTimezone('Europe/London')->format('H:i'));
        $this->artisan('app:push:send')->expectsOutputToContain('Sent 0')->assertSuccessful();
        Http::assertNothingSent();

        // The morning: it goes, and Apple says the token is gone, so it's forgotten.
        $this->travel(9)->hours();
        $this->artisan('app:push:send')->expectsOutputToContain('forgot 1')->assertSuccessful();
        $this->assertNull(AppDevice::query()->sole()->push_token_hash);

        // One that couldn't go for 12 hours is dropped (a new sign-in: the phone's hour is up).
        $this->asApp($this->device($ada))->putJson('/api/account/devices/current/push', ['platform' => 'apns', 'token' => self::APNS_TOKEN, 'environment' => 'production'])->assertOk();
        Bell::tell($ada->id, 'featured', null, Harmony::factory()->create(['user_id' => $ada->id]));
        $this->travel(13)->hours();
        $this->artisan('app:push:send')->expectsOutputToContain('dropped 1')->assertSuccessful();
    }

    public function test_nothing_is_queued_while_push_is_off(): void
    {
        $ada = User::factory()->create();
        $this->asApp($this->device($ada))->putJson('/api/account/devices/current/push', ['platform' => 'fcm', 'token' => str_repeat('f', 40)])->assertOk();

        Bell::tell($ada->id, 'follow', User::factory()->create());
        $this->assertSame(0, AppPush::query()->count());
    }
}
