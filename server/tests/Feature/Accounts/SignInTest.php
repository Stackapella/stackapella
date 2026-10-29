<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Actions\Users\ManageSignInMethods;
use App\Enums\BlockType;
use App\Mail\Account\NewSignIn;
use App\Mail\Account\ResetPassword;
use App\Models\Block;
use App\Models\User;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\PasswordFailures;
use App\Support\Accounts\SignInSession;
use App\Support\Flags;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Tests\TestCase;

final class SignInTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-fine-long-passphrase-42';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function signIn(string $email = 'ada@example.com', string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/auth/sign-in', ['email' => $email, 'password' => $password, 'remember' => true]);
    }

    public function test_the_right_password_signs_in_and_one_answer_covers_every_wrong_one(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);

        $this->assertMatchesContract($this->signIn(password: 'not-the-password'), 'auth.signin', 401);
        $this->assertMatchesContract($this->signIn('nobody@example.com'), 'auth.signin', 401);
        $this->assertGuest();

        $response = $this->signIn('ADA@example.com');
        $this->assertMatchesContract($response, 'auth.signin', 200);
        $response->assertJsonPath('data.email', 'ada@example.com');
        $this->assertAuthenticatedAs($user);
    }

    public function test_ten_wrong_passwords_lock_the_account_for_15_minutes(): void
    {
        User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        config(['limits.sign-in.ip_and_email.max' => 100, 'limits.sign-in.ip.max' => 100]);

        for ($i = 0; $i < 10; $i++) {
            $this->signIn(password: 'wrong-password-'.$i)->assertStatus(401);
        }

        $locked = $this->signIn();
        $this->assertMatchesContract($locked, 'auth.signin', 429);
        $locked->assertHeader('Retry-After');

        $this->travel(16)->minutes();
        $this->assertMatchesContract($this->signIn(), 'auth.signin', 200);
    }

    public function test_one_network_failing_on_purpose_doesnt_keep_the_owner_out(): void
    {
        User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        config(['limits.sign-in.ip_and_email.max' => 1000, 'limits.sign-in.ip.max' => 1000]);
        $from = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        for ($i = 0; $i < 10; $i++) {
            $from('203.0.113.7')->signIn(password: 'wrong-password-'.$i)->assertStatus(401);
        }

        $from('203.0.113.7')->signIn()->assertStatus(429);
        // The owner, on their own network, signs in.
        $this->app['auth']->forgetGuards();
        $from('198.51.100.20')->signIn()->assertOk();

        // Guessing spread over many networks still stops, for every network: 50 in the hour, the
        // first network's 10 among them.
        $this->app['auth']->forgetGuards();
        for ($i = 0; $i < PasswordFailures::MAX_EVERYWHERE - 10; $i++) {
            $from('192.0.2.'.(10 + intdiv($i, 9)))->signIn(password: 'wrong-password-'.$i)->assertStatus(401);
        }
        $from('198.51.100.21')->signIn()->assertStatus(429);
    }

    public function test_an_address_with_no_account_waits_the_same_after_10_wrong_passwords(): void
    {
        config(['limits.sign-in.ip_and_email.max' => 100, 'limits.sign-in.ip.max' => 100]);

        for ($i = 0; $i < 10; $i++) {
            $this->signIn('nobody@example.com', 'wrong-password-'.$i)->assertStatus(401);
        }

        $this->assertMatchesContract($this->signIn('nobody@example.com', 'wrong-password-x'), 'auth.signin', 429);
    }

    public function test_unverified_suspended_and_blocked_accounts_are_told_only_with_the_right_password(): void
    {
        User::factory()->withPassword()->unverified()->create(['email' => 'new@example.com']);
        $this->assertMatchesContract($this->signIn('new@example.com', 'wrong-password-1'), 'auth.signin', 401);
        $this->assertMatchesContract($this->signIn('new@example.com'), 'auth.signin', 403);

        User::factory()->withPassword()->suspended()->create(['email' => 'gone@example.com']);
        $this->assertMatchesContract($this->signIn('gone@example.com', 'wrong-password-1'), 'auth.signin', 401);
        $this->signIn('gone@example.com')->assertStatus(403)->assertJsonPath('code', 'suspended');

        User::factory()->withPassword()->create(['email' => 'ada@spam.test']);
        Block::factory()->create(['type' => BlockType::EmailDomain, 'value' => 'spam.test']);
        $blocked = $this->signIn('ada@spam.test');
        $this->assertMatchesContract($blocked, 'auth.signin', 403);
        $blocked->assertJsonPath('code', 'blocked');
        $this->assertGuest();
    }

    public function test_shield_mode_checks_the_turnstile_token(): void
    {
        User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret' => 'secret']);
        app(Flags::class)->set('shield_mode', 'on', null, 'An attack');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $failed = $this->postJson('/api/auth/sign-in', ['email' => 'ada@example.com', 'password' => self::PASSWORD, 'turnstileToken' => 'bad']);
        $this->assertMatchesContract($failed, 'auth.signin', 403);
        $failed->assertJsonPath('code', 'turnstile_failed');
    }

    public function test_a_sign_in_from_a_new_browser_is_mentioned_by_email(): void
    {
        User::factory()->withPassword()->create(['email' => 'ada@example.com']);

        $this->signIn()->assertOk();
        Mail::assertNotQueued(NewSignIn::class);

        $this->postJson('/api/auth/sign-out');
        $this->withUnencryptedCookie('stackapella_device', str_repeat('b', 40))->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari/604.1')->signIn()->assertOk();
        Mail::assertQueued(NewSignIn::class, fn (NewSignIn $mail): bool => $mail->browser === 'Safari, iPhone');
    }

    public function test_signing_out_and_signing_out_everywhere(): void
    {
        $user = User::factory()->withPassword()->create();
        $token = $user->remember_token;

        $this->actingAs($user);
        $this->assertMatchesContract($this->postJson('/api/auth/sign-out'), 'auth.signout', 204);
        $this->assertGuest();

        $this->actingAs($user);
        $this->assertMatchesContract($this->postJson('/api/auth/sign-out-everywhere'), 'auth.signout.everywhere', 204);
        $this->assertGuest();
        $this->assertNotSame($token, $user->refresh()->remember_token);
    }

    public function test_a_forgotten_password_is_reset_by_the_link_which_signs_in(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);

        $this->assertMatchesContract($this->postJson('/api/auth/password/forgot', ['email' => 'ada@example.com']), 'auth.password.forgot', 202);
        $this->assertMatchesContract($this->postJson('/api/auth/password/forgot', ['email' => 'nobody@example.com']), 'auth.password.forgot', 202);

        $token = null;
        Mail::assertQueued(ResetPassword::class, function (ResetPassword $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->hasTo('ada@example.com');
        });
        Mail::assertQueuedCount(1);

        $weak = $this->postJson('/api/auth/password/reset', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'qwertyuiop']);
        $this->assertMatchesContract($weak, 'auth.password.reset', 422);

        $reset = $this->postJson('/api/auth/password/reset', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'another-fine-passphrase']);
        $this->assertMatchesContract($reset, 'auth.password.reset', 200);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('another-fine-passphrase', (string) $user->refresh()->password));

        $this->assertMatchesContract($this->postJson('/api/auth/password/reset', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'yet-another-passphrase']), 'auth.password.reset', 410);
    }

    public function test_a_suspended_account_gets_no_reset_link_and_cannot_use_an_old_one(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $token = app('auth.password.broker')->createToken($user);
        $user->forceFill(['suspended_at' => now()])->save();

        $this->postJson('/api/auth/password/forgot', ['email' => 'ada@example.com'])->assertStatus(202);
        Mail::assertNothingQueued();
        $this->assertMatchesContract($this->postJson('/api/auth/password/reset', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'another-fine-passphrase']), 'auth.password.reset', 403);
        $this->assertGuest();
    }

    public function test_the_first_google_sign_in_from_the_app_makes_an_account_and_returns_to_the_page(): void
    {
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-1', 'name' => 'Ada', 'email' => 'Ada@Example.com'])->setRaw(['email_verified' => true]));

        $this->post('/auth/google', ['intended' => '/songs', 'ref' => 'hn'])->assertRedirect();
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/songs?signedIn=google');

        $user = User::query()->where('google_id', 'g-1')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('hn', $user->ref);
        $this->assertNull($user->age_confirmed_at, 'The app asks "13 or over" once.');
    }

    public function test_signing_in_with_google_before_launch_opens_the_app_the_first_time(): void
    {
        // Hillel's "I had to sign in with Google twice": a fresh browser has no beta pass, and the
        // app page Google returns to showed "coming soon" until a second sign-in from /signin.
        config(['app.coming_soon' => true]);
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-1', 'name' => 'Ada', 'email' => 'ada@example.com'])->setRaw(['email_verified' => true]));

        $this->get('/signin')->assertOk();
        $this->post('/auth/google', ['intended' => '/'])->assertRedirect();
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/?signedIn=google');

        $home = $this->get('/?signedIn=google')->assertOk();
        $this->assertStringNotContainsString(__('meta.coming_soon.title'), (string) $home->getContent());
        $this->assertNotNull(collect($home->headers->getCookies())->first(fn ($c) => $c->getName() === BetaPass::COOKIE), 'Signed in, the page gives the beta pass too.');

        // Signed out again, a session alone still doesn't open it.
        $this->post('/api/auth/sign-out');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertSee(__('meta.coming_soon.title'));
    }

    public function test_a_lost_session_at_googles_return_goes_back_to_sign_in(): void
    {
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-1', 'name' => 'Ada', 'email' => 'ada@example.com'])->setRaw(['email_verified' => true]));

        // No pending sign-in in this session (it was lost on the way): never the admin's page.
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect();
        $this->assertStringNotContainsString('/admin', (string) $this->get('/auth/google/callback?code=x&state=y')->headers->get('Location'));
    }

    public function test_a_refused_provider_sign_in_returns_to_the_app_page_with_the_reason(): void
    {
        User::factory()->suspended()->create(['google_id' => 'g-1']);
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-1', 'name' => 'Ada', 'email' => 'ada@example.com'])->setRaw(['email_verified' => true]));

        $this->post('/auth/google', ['intended' => '/songs']);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/songs?authError=suspended');
        $this->assertGuest();

        app(Flags::class)->set('pause_signups', 'all', null, 'Bots');
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-2', 'name' => 'Bo', 'email' => 'bo@example.com'])->setRaw(['email_verified' => true]));
        $this->post('/auth/google', ['intended' => '/']);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/?authError=paused');
    }

    public function test_google_is_linked_from_settings_but_never_taken_from_another_account(): void
    {
        $user = User::factory()->withPassword()->create();
        User::factory()->create(['google_id' => 'someone-elses']);
        $this->actingAs($user)->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp() - 3600]);

        // Adding a way in needs a recent sign-in.
        $this->post('/auth/google', ['intended' => '/settings', 'link' => '1'])->assertRedirect('/settings?authError=recent_sign_in_required');
        $this->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp()]);

        Socialite::fake('google', (new ProviderUser)->map(['id' => 'someone-elses', 'name' => 'Ada', 'email' => 'x@example.com'])->setRaw(['email_verified' => true]));
        $this->post('/auth/google', ['intended' => '/settings', 'link' => '1']);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/settings?authError=linked_elsewhere');

        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-mine', 'name' => 'Ada', 'email' => 'x@example.com'])->setRaw(['email_verified' => true]));
        $this->post('/auth/google', ['intended' => '/settings', 'link' => '1']);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/settings?linked=google');
        $this->assertSame('g-mine', $user->refresh()->google_id);
    }

    public function test_apple_returns_by_a_cross_site_post_and_finishes_on_a_same_site_page(): void
    {
        Socialite::fake('apple', (new ProviderUser)->map(['id' => 'apple-1', 'name' => 'Ada', 'email' => 'relay@privaterelay.appleid.com']));

        $start = $this->post('/auth/apple', ['intended' => '/songs']);
        $start->assertRedirect();
        $cookie = collect($start->headers->getCookies())->first(fn ($c) => $c->getName() === 'stackapella_apple');
        $this->assertNotNull($cookie);
        $state = CookieValuePrefix::remove(decrypt((string) $cookie->getValue(), false));

        $this->post('/auth/apple/callback', ['state' => 'forged'])->assertRedirect('/?authError=expired');

        $callback = $this->withCookie('stackapella_apple', $state)->post('/auth/apple/callback', ['state' => $state, 'code' => 'x']);
        $callback->assertRedirectContains('/auth/apple/complete?code=');

        $callback->assertCookieMissing((string) config('session.cookie'));

        // Only the browser holding the state cookie can finish.
        $location = (string) $callback->headers->get('Location');
        $this->withCookie('stackapella_apple', 'another-browser')->get($location)->assertRedirect('/?authError=expired');
        $this->assertGuest();
    }

    public function test_the_webs_apple_token_is_kept_and_revoked_with_the_services_id_when_apple_is_removed(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
        openssl_pkey_export($key, $pem);
        config(['services.apple.client_id' => 'com.stackapella.web', 'services.apple.app_bundle_id' => 'com.stackapella.app', 'services.apple.team_id' => 'TEAM', 'services.apple.key_id' => 'KEY', 'services.apple.private_key' => $pem]);
        Http::fake(['appleid.apple.com/auth/revoke' => Http::response([])]);
        Socialite::fake('apple', (new ProviderUser)->map(['id' => 'apple-1', 'name' => 'Ada', 'email' => 'relay@privaterelay.appleid.com'])->setRefreshToken('web-refresh'));

        $start = $this->post('/auth/apple', ['intended' => '/songs']);
        $cookie = collect($start->headers->getCookies())->first(fn ($c) => $c->getName() === 'stackapella_apple');
        $state = CookieValuePrefix::remove(decrypt((string) $cookie->getValue(), false));
        $callback = $this->withCookie('stackapella_apple', $state)->post('/auth/apple/callback', ['state' => $state, 'code' => 'x']);
        $this->withCookie('stackapella_apple', $state)->get((string) $callback->headers->get('Location'))->assertRedirect('/songs?signedIn=apple');

        $ada = User::query()->where('apple_id', 'apple-1')->sole();
        $this->assertSame('web-refresh', $ada->apple_web_refresh_token);
        $this->assertNotSame('web-refresh', DB::table('users')->where('id', $ada->id)->value('apple_web_refresh_token'));

        $ada->forceFill(['password' => self::PASSWORD])->save();
        app(ManageSignInMethods::class)->remove($ada, 'apple');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'auth/revoke') && $request['token'] === 'web-refresh' && $request['client_id'] === 'com.stackapella.web');
        $this->assertNull($ada->refresh()->apple_web_refresh_token);
    }

    public function test_apple_is_sent_a_nonce_its_id_token_must_carry_back(): void
    {
        config(['services.apple.client_id' => 'com.stackapella.web', 'services.apple.client_secret' => 'secret']);

        $start = $this->post('/auth/apple', ['intended' => '/songs']);
        $location = (string) $start->headers->get('Location');
        $this->assertStringStartsWith('https://appleid.apple.com/auth/authorize', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(40, strlen((string) ($query['nonce'] ?? '')));
        $this->assertSame($query['nonce'], Cache::get('auth:apple:'.$query['state'])['nonce'] ?? null);
    }

    public function test_a_stale_token_on_the_sign_in_form_goes_back_to_the_app_page(): void
    {
        // Laravel skips the CSRF check while the environment is "testing"; this one checks it.
        $this->app->instance('env', 'production');

        $this->post('/auth/google', ['intended' => '/songs', '_token' => 'stale'])->assertRedirect('/songs?authError=expired');
        $this->post('/auth/google', ['intended' => '/admin/people', '_token' => 'stale'])->assertRedirectContains('/admin/sign-in?intended=%2Fadmin%2Fpeople&error=expired');

        // The XSRF-TOKEN cookie's value (what the app can read) works as the form's `_token`.
        $page = $this->get('/admin/sign-in');
        $cookie = collect($page->headers->getCookies())->first(fn ($c) => $c->getName() === 'XSRF-TOKEN');
        $this->post('/auth/google', ['intended' => '/songs', '_token' => (string) $cookie?->getValue()])->assertRedirectContains('accounts.google.com');
        $this->post('/auth/google', ['intended' => '/songs', '_token' => session()->token()])->assertRedirectContains('accounts.google.com');
    }

    public function test_apple_finishes_in_the_browser_that_started(): void
    {
        Socialite::fake('apple', (new ProviderUser)->map(['id' => 'apple-1', 'name' => 'Ada', 'email' => 'relay@privaterelay.appleid.com']));

        $start = $this->post('/auth/apple', ['intended' => '/songs']);
        $cookie = collect($start->headers->getCookies())->first(fn ($c) => $c->getName() === 'stackapella_apple');
        $state = CookieValuePrefix::remove(decrypt((string) $cookie?->getValue(), false));

        $callback = $this->withCookie('stackapella_apple', $state)->post('/auth/apple/callback', ['state' => $state, 'code' => 'x']);
        $this->withCookie('stackapella_apple', $state)->get((string) $callback->headers->get('Location'))->assertRedirect('/songs?signedIn=apple');
        $this->assertSame('apple-1', User::query()->firstOrFail()->apple_id);
        $this->assertAuthenticated();
    }

    public function test_a_link_started_by_one_account_never_lands_on_another(): void
    {
        $attacker = User::factory()->withPassword()->create();
        $victim = User::factory()->withPassword()->create();
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-attacker', 'name' => 'X', 'email' => 'x@example.com'])->setRaw(['email_verified' => true]));

        $this->actingAs($attacker)->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp()]);
        $this->post('/auth/google', ['intended' => '/settings', 'link' => '1']);

        $this->actingAs($victim);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/settings?authError=expired');
        $this->assertNull($victim->refresh()->google_id);
        $this->assertNull($attacker->refresh()->google_id);
    }
}
