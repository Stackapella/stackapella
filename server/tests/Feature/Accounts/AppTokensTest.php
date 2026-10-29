<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Actions\Users\DeleteAccount;
use App\Enums\BlockType;
use App\Http\Middleware\AuthenticateAppDevice;
use App\Http\Middleware\PreventRequestForgery;
use App\Mail\Account\NewSignIn;
use App\Mail\Account\VerifyEmailAddress;
use App\Models\AppDevice;
use App\Models\Block;
use App\Models\EarlyAccessLink;
use App\Models\User;
use App\Services\Auth\ProviderTokens;
use App\Support\Accounts\EarlyAccess;
use App\Support\Flags;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The native apps' device tokens (contract/api.md, "The apps: device tokens"): signing in gives a
 * device an access and a refresh token; a bearer request is signed in with no cookies and no CSRF;
 * refresh tokens rotate, and a stolen one signs the device out.
 */
final class AppTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private const DEVICE = ['name' => "Ada's iPhone", 'platform' => 'ios', 'model' => 'iPhone17,1', 'appVersion' => '0.1.0', 'replace' => null];

    private function signIn(?User $user = null): TestResponse
    {
        $user ??= User::factory()->withPassword()->create(['email' => 'ada@example.com']);

        return $this->postJson('/api/auth/app/sign-in', ['email' => $user->email, 'password' => 'a-fine-long-passphrase-42', 'device' => self::DEVICE]);
    }

    /**
     * A bearer request with no cookies at all, as an app sends it.
     */
    public function test_signing_out_everywhere_from_the_app_ends_the_web_sessions_too(): void
    {
        // As in production: web sessions are rows (an app's own request runs on the array driver).
        config(['session.driver' => 'database']);
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $access = (string) $this->signIn($user)->json('data.accessToken');
        DB::table('sessions')->insert(['id' => str_repeat('s', 40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(), 'ip_address' => null, 'user_agent' => null]);
        DB::table('sessions')->insert(['id' => str_repeat('t', 40), 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time(), 'ip_address' => null, 'user_agent' => null]);

        $this->asApp($access)->postJson('/api/auth/sign-out-everywhere')->assertNoContent();

        $this->assertSame([str_repeat('t', 40)], DB::table('sessions')->pluck('id')->all());
        $this->assertSame('database', config('session.driver'));
        $this->assertNull(config('session.stored_driver'));
    }

    private function asApp(string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    }

    public function test_an_app_signs_in_and_its_requests_need_no_session_or_csrf(): void
    {
        $signed = $this->signIn();
        $this->assertMatchesContract($signed, 'auth.app.signin', 200);
        $access = (string) $signed->json('data.accessToken');
        $this->assertStringStartsWith('sat_', $access);
        $this->assertStringStartsWith('srt_', (string) $signed->json('data.refreshToken'));
        $signed->assertJsonPath('data.device.current', true)->assertJsonPath('data.account.email', 'ada@example.com');
        // Only hashes are kept.
        $this->assertSame(hash('sha256', $access), AppDevice::query()->sole()->access_hash);

        $account = $this->asApp($access)->getJson('/api/account')->assertOk()->assertJsonPath('data.email', 'ada@example.com');
        $this->assertSame([], $account->headers->getCookies());
        // A change with no CSRF token: fine for a bearer request.
        $this->asApp($access)->patchJson('/api/account', ['name' => 'Ada R.'])->assertOk()->assertJsonPath('data.name', 'Ada R.');

        // Wrong password: the web's answer.
        $this->app['auth']->forgetGuards();
        $this->assertMatchesContract($this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'nope', 'device' => self::DEVICE]), 'auth.app.signin', 401);
        $this->assertMatchesContract($this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'x', 'device' => ['name' => 'X', 'platform' => 'beos']]), 'auth.app.signin', 422);

        // An unknown token, and one past its hour.
        $this->asApp('sat_nope')->getJson('/api/account')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
        $this->travel(61)->minutes();
        $this->asApp($access)->getJson('/api/account')->assertStatus(401)->assertJsonPath('code', 'token_expired');
    }

    public function test_the_api_checks_csrf_with_the_check_that_lets_the_apps_through(): void
    {
        // Laravel skips CSRF in tests, so this is the guard: the api group copies the web group,
        // and must hold our check (which passes app requests), after the bearer middleware.
        $api = app(Kernel::class)->getMiddlewareGroups()['api'];
        $this->assertSame(AuthenticateAppDevice::class, $api[0]);
        $this->assertContains(PreventRequestForgery::class, $api);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, $api);
    }

    public function test_refresh_tokens_rotate_and_a_stolen_one_signs_the_device_out(): void
    {
        $signed = $this->signIn()->assertOk();
        $first = (string) $signed->json('data.refreshToken');

        $refreshed = $this->postJson('/api/auth/app/refresh', ['refreshToken' => $first]);
        $this->assertMatchesContract($refreshed, 'auth.app.refresh', 200);
        $second = (string) $refreshed->json('data.refreshToken');
        $this->assertNotSame($first, $second);
        $this->asApp((string) $refreshed->json('data.accessToken'))->getJson('/api/account')->assertOk();

        // The first one again within the minute (a reply lost, or a replay): the same pair, and
        // the device's current tokens still work.
        $this->app['auth']->forgetGuards();
        $again = $this->postJson('/api/auth/app/refresh', ['refreshToken' => $first])->assertOk();
        $this->assertSame([$refreshed->json('data.accessToken'), $second], [$again->json('data.accessToken'), $again->json('data.refreshToken')]);
        $this->assertSame(hash('sha256', $second), AppDevice::query()->sole()->refresh_hash);

        // After the minute: taken as stolen, and the device is signed out.
        $this->travel(2)->minutes();
        $reused = $this->postJson('/api/auth/app/refresh', ['refreshToken' => $first]);
        $this->assertMatchesContract($reused, 'auth.app.refresh', 401);
        $reused->assertJsonPath('code', 'token_reused');
        $this->assertNotNull(AppDevice::query()->sole()->revoked_at);
        $this->postJson('/api/auth/app/refresh', ['refreshToken' => $second])->assertStatus(401)->assertJsonPath('code', 'refresh_invalid');
        $this->postJson('/api/auth/app/refresh', ['refreshToken' => 'srt_unknown'])->assertStatus(401)->assertJsonPath('code', 'refresh_invalid');
    }

    public function test_devices_are_listed_signed_out_and_signed_in_again_on_the_same_one(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $phone = $this->signIn($user)->assertOk();
        $this->app['auth']->forgetGuards();
        $laptop = $this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'device' => [...self::DEVICE, 'name' => 'A Mac', 'platform' => 'macos']])->assertOk();

        $list = $this->asApp((string) $phone->json('data.accessToken'))->getJson('/api/account/devices');
        $this->assertMatchesContract($list, 'account.devices.list', 200);
        $list->assertJsonCount(2, 'data');
        $this->assertSame([true], array_values(array_filter(array_column((array) $list->json('data'), 'current'))));

        // Signing in again on the phone: the same device, a new sign-in time.
        $this->travel(30)->minutes();
        $this->app['auth']->forgetGuards();
        $again = $this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'device' => [...self::DEVICE, 'replace' => $phone->json('data.device.id')]])->assertOk();
        $again->assertJsonPath('data.device.id', $phone->json('data.device.id'));
        $this->assertSame(2, AppDevice::query()->count());

        // The laptop signed out from the phone; then the phone signs itself out.
        $phoneToken = (string) $again->json('data.accessToken');
        $this->assertMatchesContract($this->asApp($phoneToken)->deleteJson('/api/account/devices/'.$laptop->json('data.device.id')), 'account.devices.delete', 204);
        $this->asApp((string) $laptop->json('data.accessToken'))->getJson('/api/account')->assertStatus(401);
        $this->assertMatchesContract($this->asApp($phoneToken)->deleteJson('/api/account/devices/nope'), 'account.devices.delete', 404);
        $this->assertMatchesContract($this->asApp($phoneToken)->postJson('/api/auth/app/sign-out'), 'auth.app.signout', 204);
        $this->asApp($phoneToken)->getJson('/api/account')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
    }

    public function test_signing_out_everywhere_and_a_new_password_sign_out_the_apps(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $access = (string) $this->signIn($user)->json('data.accessToken');

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->postJson('/api/auth/sign-out-everywhere')->assertNoContent();
        $this->asApp($access)->getJson('/api/account')->assertStatus(401);

        // A password changed from one app keeps that app signed in, and no other.
        $this->app['auth']->forgetGuards();
        $mine = (string) $this->signIn($user)->json('data.accessToken');
        $this->app['auth']->forgetGuards();
        $other = (string) $this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'device' => [...self::DEVICE, 'name' => 'Another']])->json('data.accessToken');
        $this->asApp($mine)->putJson('/api/account/password', ['currentPassword' => 'a-fine-long-passphrase-42', 'password' => 'another-fine-passphrase-9'])->assertOk();
        $this->asApp($mine)->getJson('/api/account')->assertOk();
        $this->asApp($other)->getJson('/api/account')->assertStatus(401);
    }

    public function test_the_emailed_link_opened_in_the_app_verifies_and_signs_in_there(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 30000])->assertStatus(202);
        $token = '';
        Mail::assertQueued(VerifyEmailAddress::class, function (VerifyEmailAddress $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $verified = $this->postJson('/api/auth/app/verify', ['token' => $token, 'device' => self::DEVICE]);
        $this->assertMatchesContract($verified, 'auth.app.verify', 200);
        $verified->assertJsonPath('data.account.emailVerified', true);
        $this->assertSame([], $verified->headers->getCookies());
        $this->assertMatchesContract($this->postJson('/api/auth/app/verify', ['token' => $token, 'device' => self::DEVICE]), 'auth.app.verify', 410);
    }

    public function test_refreshes_are_limited_per_account_and_unknown_bearer_tokens_per_network(): void
    {
        $refresh = (string) $this->signIn()->json('data.refreshToken');

        foreach (range(1, 60) as $n) {
            // Spaced out, so the API's own limit per network (60 a minute) isn't what stops it.
            $this->travel(2)->seconds();
            $refresh = (string) $this->postJson('/api/auth/app/refresh', ['refreshToken' => $refresh])->assertOk()->json('data.refreshToken');
        }

        $this->assertMatchesContract($this->postJson('/api/auth/app/refresh', ['refreshToken' => $refresh]), 'auth.app.refresh', 429);

        foreach (range(1, AuthenticateAppDevice::FAILURES_PER_MINUTE) as $n) {
            $this->asApp('sat_guess'.$n)->getJson('/api/account')->assertStatus(401);
        }

        $this->asApp('sat_guess')->getJson('/api/account')->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    }

    public function test_an_app_request_saves_no_session_and_a_new_device_is_emailed_by_its_platform(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);

        // The account's first sign-in anywhere: no email.
        $access = (string) $this->signIn($user)->json('data.accessToken');
        $this->asApp($access)->getJson('/api/account')->assertOk();
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame('database', config('session.driver'));
        Mail::assertNothingOutgoing();

        // A second device: named by its platform, never by the name sent.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'device' => [...self::DEVICE, 'name' => 'Call +1 555 0100 now', 'platform' => 'android']])->assertOk();
        Mail::assertQueued(NewSignIn::class, fn (NewSignIn $mail): bool => $mail->app && $mail->browser === 'Android');
        Mail::assertQueuedCount(1);
    }

    public function test_the_app_pass_opens_the_app_before_launch(): void
    {
        config(['app.coming_soon' => true]);
        $code = substr(EarlyAccess::url(EarlyAccess::current(EarlyAccessLink::PREVIEW)), strrpos(EarlyAccess::url(EarlyAccess::current(EarlyAccessLink::PREVIEW)), '/') + 1);

        $this->getJson('/api/auth/config')->assertJsonPath('data.comingSoon', true);
        $claimed = $this->postJson('/api/app-pass', ['kind' => 'preview', 'code' => $code]);
        $this->assertMatchesContract($claimed, 'appPass.claim', 200);
        $pass = (string) $claimed->json('data.pass');
        $this->assertStringStartsWith('sap_', $pass);
        $this->withHeaders(['X-App-Pass' => $pass])->getJson('/api/auth/config')->assertJsonPath('data.comingSoon', false);
        $this->withHeaders(['X-App-Pass' => 'sap_1.forged'])->getJson('/api/auth/config')->assertJsonPath('data.comingSoon', true);

        $this->assertMatchesContract($this->postJson('/api/app-pass', ['kind' => 'invite', 'code' => $code]), 'appPass.claim', 410);
        $this->assertMatchesContract($this->postJson('/api/app-pass', ['kind' => 'nope', 'code' => $code]), 'appPass.claim', 422);
    }

    public function test_google_and_apple_tokens_from_the_device_are_checked_and_apple_is_revoked_on_deletion(): void
    {
        [$private, $jwks] = self::keys();
        config(['services.google.app_client_ids' => ['ios-client'], 'services.apple.app_bundle_id' => 'com.stackapella.app', 'services.apple.team_id' => 'TEAM', 'services.apple.key_id' => 'KEY', 'services.apple.private_key' => self::appleKey()]);
        $token = fn (array $claims): string => JWT::encode([...$claims, 'exp' => time() + 600, 'iat' => time()], $private, 'RS256', 'k1');
        $appleCode = fn (string $sub): array => ['refresh_token' => 'apple-refresh', 'id_token' => $token(['iss' => 'https://appleid.apple.com', 'aud' => 'com.stackapella.app', 'sub' => $sub])];
        Http::fake([
            // Google's keys were cached before it rotated to k1: an unknown key id fetches them again.
            ProviderTokens::GOOGLE_KEYS => Http::sequence()->push(['keys' => []])->push($jwks),
            ProviderTokens::APPLE_KEYS => Http::response($jwks),
            'appleid.apple.com/auth/token' => Http::sequence()->push($appleCode('someone-else'))->push($appleCode('a-1')),
            'appleid.apple.com/auth/revoke' => Http::response([]),
        ]);
        $googleToken = $token(['iss' => 'https://accounts.google.com', 'aud' => 'ios-client', 'sub' => 'g-1', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
        $google = $this->postJson('/api/auth/app/google', ['idToken' => $googleToken, 'device' => self::DEVICE]);
        $this->assertMatchesContract($google, 'auth.app.google', 200);
        $google->assertJsonPath('data.account.email', 'ada@example.com');
        $this->assertSame('g-1', User::query()->where('email', 'ada@example.com')->value('google_id'));
        // The same token again, another audience, an unverified address, or a forged token: refused.
        $this->app['auth']->forgetGuards();
        $this->assertMatchesContract($this->postJson('/api/auth/app/google', ['idToken' => $googleToken, 'device' => self::DEVICE]), 'auth.app.google', 401);
        $this->assertMatchesContract($this->postJson('/api/auth/app/google', ['idToken' => $token(['iss' => 'https://accounts.google.com', 'aud' => 'someone-else', 'sub' => 'g-2', 'email_verified' => true]), 'device' => self::DEVICE]), 'auth.app.google', 401);
        $unverified = $this->postJson('/api/auth/app/google', ['idToken' => $token(['iss' => 'https://accounts.google.com', 'aud' => 'ios-client', 'sub' => 'g-2', 'email_verified' => false]), 'device' => self::DEVICE]);
        $this->assertMatchesContract($unverified, 'auth.app.google', 403);
        $unverified->assertJsonPath('code', 'unverified');
        $this->postJson('/api/auth/app/google', ['idToken' => 'not.a.token', 'device' => self::DEVICE])->assertStatus(401)->assertJsonPath('code', 'invalid_token');

        // Google with the launch's nonce: a token that carries one must come with it.
        $launch = str_repeat('l', 40);
        $withNonce = fn (): string => $token(['iss' => 'https://accounts.google.com', 'aud' => 'ios-client', 'sub' => 'g-1', 'email_verified' => true, 'nonce' => $launch, 'jti' => uniqid()]);
        $this->postJson('/api/auth/app/google', ['idToken' => $withNonce(), 'device' => self::DEVICE])->assertStatus(401)->assertJsonPath('code', 'invalid_token');
        $this->postJson('/api/auth/app/google', ['idToken' => $withNonce(), 'nonce' => str_repeat('x', 40), 'device' => self::DEVICE])->assertStatus(401);
        $this->postJson('/api/auth/app/google', ['idToken' => $withNonce(), 'nonce' => $launch, 'device' => self::DEVICE])->assertOk();

        // Apple: this sign-in's nonce, which Apple was given as its SHA-256.
        $nonce = str_repeat('n', 40);
        $appleToken = fn (array $claims): string => $token(['iss' => 'https://appleid.apple.com', 'aud' => 'com.stackapella.app', 'nonce' => hash('sha256', $nonce), ...$claims]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/app/apple', ['identityToken' => $appleToken(['sub' => 'a-1', 'jti' => 'none']), 'authorizationCode' => 'code', 'device' => self::DEVICE])->assertStatus(422);
        $this->assertMatchesContract($this->postJson('/api/auth/app/apple', ['identityToken' => $appleToken(['sub' => 'a-1', 'jti' => 'wrong']), 'nonce' => str_repeat('w', 40), 'authorizationCode' => 'code', 'device' => self::DEVICE]), 'auth.app.apple', 401);
        $apple = $this->postJson('/api/auth/app/apple', ['identityToken' => $appleToken(['sub' => 'a-1', 'email' => 'kim@privaterelay.appleid.com']), 'nonce' => $nonce, 'authorizationCode' => 'code', 'name' => 'Kim', 'device' => self::DEVICE]);
        $this->assertMatchesContract($apple, 'auth.app.apple', 200);
        $apple->assertJsonPath('data.account.name', 'Kim');
        // Apple's code was someone else's: signed in, but nothing kept.
        $kim = User::query()->where('apple_id', 'a-1')->sole();
        $this->assertNull($kim->apple_refresh_token);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/app/apple', ['identityToken' => $appleToken(['sub' => 'a-1', 'jti' => 'again']), 'nonce' => $nonce, 'authorizationCode' => 'code', 'device' => self::DEVICE])->assertOk();
        $this->assertSame('apple-refresh', $kim->refresh()->apple_refresh_token);

        app(DeleteAccount::class)->handle($kim, null);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'auth/revoke') && $request['token'] === 'apple-refresh');
        $this->assertSame(0, AppDevice::query()->where('user_id', $kim->id)->count());
    }

    public function test_replace_needs_the_devices_own_token_and_a_valid_token_passes_a_busy_network(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $phone = $this->signIn($user)->assertOk();

        // Someone with the password names the phone's id, without its token: a new device, emailed.
        $this->app['auth']->forgetGuards();
        $other = $this->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'device' => [...self::DEVICE, 'replace' => $phone->json('data.device.id')]])->assertOk();
        $this->assertNotSame($phone->json('data.device.id'), $other->json('data.device.id'));
        Mail::assertQueuedCount(1);
        $this->asApp((string) $phone->json('data.accessToken'))->getJson('/api/account')->assertOk();

        // The phone itself, past its hour: the same device.
        $this->travel(2)->hours();
        $this->app['auth']->forgetGuards();
        $again = $this->asApp((string) $phone->json('data.accessToken'))->postJson('/api/auth/app/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'device' => [...self::DEVICE, 'replace' => $phone->json('data.device.id')]])->assertOk();
        $again->assertJsonPath('data.device.id', $phone->json('data.device.id'));

        // A network over its limit of failing tokens still lets a valid one through.
        foreach (range(1, AuthenticateAppDevice::FAILURES_PER_MINUTE + 1) as $n) {
            $this->asApp('sat_guess'.$n)->getJson('/api/account');
        }

        $this->asApp('sat_guess')->getJson('/api/account')->assertStatus(429);
        $this->asApp((string) $again->json('data.accessToken'))->getJson('/api/account')->assertOk();
    }

    public function test_linking_keeps_the_sign_in_time_says_token_expired_past_the_hour_and_paused_is_423(): void
    {
        [$private, $jwks] = self::keys();
        config(['services.google.app_client_ids' => ['ios-client']]);
        Http::fake([ProviderTokens::GOOGLE_KEYS => Http::response($jwks)]);
        // Our clock a little behind Google's: a token minted "in the future" still counts.
        $token = fn (string $sub): string => JWT::encode(['iss' => 'https://accounts.google.com', 'aud' => 'ios-client', 'sub' => $sub, 'email' => $sub.'@example.com', 'email_verified' => true, 'jti' => uniqid(), 'exp' => time() + 600, 'iat' => time() + 20], $private, 'RS256', 'k1');

        $access = (string) $this->signIn()->json('data.accessToken');
        $signedInAt = AppDevice::query()->sole()->signed_in_at;
        $this->travel(5)->minutes();
        $linked = $this->asApp($access)->postJson('/api/auth/app/google', ['idToken' => $token('g-1'), 'link' => true, 'device' => self::DEVICE])->assertOk();
        $this->assertSame($signedInAt->toDateTimeString(), AppDevice::query()->sole()->signed_in_at->toDateTimeString());

        $this->travel(2)->hours();
        $this->asApp((string) $linked->json('data.accessToken'))->postJson('/api/auth/app/google', ['idToken' => $token('g-2'), 'link' => true, 'device' => self::DEVICE])
            ->assertStatus(401)->assertJsonPath('code', 'token_expired');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        app(Flags::class)->set('pause_signups', 'all', null, 'Bots');
        $this->assertMatchesContract($this->postJson('/api/auth/app/google', ['idToken' => $token('g-3'), 'device' => self::DEVICE]), 'auth.app.google', 423);
    }

    public function test_linking_google_needs_a_recent_sign_in_and_an_id_no_other_account_has(): void
    {
        [$private, $jwks] = self::keys();
        config(['services.google.app_client_ids' => ['ios-client']]);
        Http::fake([ProviderTokens::GOOGLE_KEYS => Http::response($jwks)]);
        $token = fn (string $sub): string => JWT::encode(['iss' => 'https://accounts.google.com', 'aud' => 'ios-client', 'sub' => $sub, 'email' => $sub.'@example.com', 'email_verified' => true, 'jti' => uniqid(), 'exp' => time() + 600, 'iat' => time()], $private, 'RS256', 'k1');
        User::factory()->create(['email' => 'kim@example.com', 'google_id' => 'g-kim']);
        $ada = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $access = (string) $this->signIn($ada)->json('data.accessToken');

        // Someone else's Google account is never added to Ada's.
        $taken = $this->asApp($access)->postJson('/api/auth/app/google', ['idToken' => $token('g-kim'), 'link' => true, 'device' => self::DEVICE]);
        $this->assertMatchesContract($taken, 'auth.app.google', 409);
        $taken->assertJsonPath('code', 'linked_elsewhere');

        // Signed in more than 10 minutes ago: sign in again first.
        $this->travel(11)->minutes();
        $stale = $this->asApp($access)->postJson('/api/auth/app/google', ['idToken' => $token('g-ada'), 'link' => true, 'device' => self::DEVICE]);
        $this->assertMatchesContract($stale, 'auth.app.google', 403);
        $stale->assertJsonPath('code', 'recent_sign_in_required');
        $this->assertNull($ada->refresh()->google_id);
    }

    /**
     * An RSA key pair, and its public half as the providers publish theirs.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function keys(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $details = (array) openssl_pkey_get_details($key);
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        return [$private, ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]]];
    }

    private static function appleKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
        openssl_pkey_export($key, $pem);

        return $pem;
    }

    public function test_the_app_sign_in_refuses_as_the_web_does_with_the_right_password_only(): void
    {
        $signIn = fn (string $email, array $with = []) => $this->postJson('/api/auth/app/sign-in', ['email' => $email, 'password' => 'a-fine-long-passphrase-42', 'device' => self::DEVICE, ...$with]);
        User::factory()->withPassword()->unverified()->create(['email' => 'new@example.com']);
        User::factory()->withPassword()->suspended()->create(['email' => 'gone@example.com']);
        User::factory()->withPassword()->create(['email' => 'spam@example.net']);
        User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        Block::factory()->create(['type' => BlockType::EmailDomain, 'value' => 'example.net']);

        foreach (['new@example.com' => 'email_unverified', 'gone@example.com' => 'suspended', 'spam@example.net' => 'blocked'] as $email => $code) {
            $refused = $signIn($email);
            $this->assertMatchesContract($refused, 'auth.app.signin', 403);
            $refused->assertJsonPath('code', $code);
        }

        // Shield mode: a Turnstile token, and a good one.
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret' => 'secret']);
        app(Flags::class)->set('shield_mode', 'on', null, 'An attack');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $this->assertMatchesContract($signIn('ada@example.com'), 'auth.app.signin', 403);
        $signIn('ada@example.com')->assertJsonPath('code', 'turnstile_required');
        $signIn('ada@example.com', ['turnstileToken' => 'bad'])->assertJsonPath('code', 'turnstile_failed');
        $this->assertSame(0, AppDevice::query()->count());
    }
}
