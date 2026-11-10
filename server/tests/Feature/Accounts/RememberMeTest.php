<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Mail\Account\SignInLinkMail;
use App\Models\AppDevice;
use App\Models\User;
use App\Support\Accounts\AppTokens;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Signed in until they sign out (Hillel, 2026-10-29: "Keep people logged in forever, set cookie to
 * remember automatically"): every sign-in sets a remember cookie of about five years, renewed on
 * each return, which signing out ends on that device and signing out everywhere ends on all.
 */
final class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_a_sign_in_is_remembered_without_asking_and_outlives_its_session(): void
    {
        User::factory()->withPassword()->create(['email' => 'ada@example.com']);

        // No checkbox: even an old client asking not to be remembered is.
        $signedIn = $this->postJson('/api/auth/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'remember' => false])->assertOk();
        $cookie = $this->recaller($signedIn);
        $this->assertGreaterThan(now()->addYears(4)->getTimestamp(), $cookie->getExpiresTime());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        // The session is gone (it expired, or the server restarted): the cookie signs them in, and
        // is set again for its full life.
        $back = $this->asNewVisit($cookie)->getJson('/api/account')->assertOk()->assertJsonPath('data.email', 'ada@example.com');
        $this->assertTrue($this->guard()->viaRemember());
        $this->assertGreaterThan(now()->addYears(4)->getTimestamp(), $this->recaller($back)->getExpiresTime());
    }

    public function test_signing_out_ends_it_on_this_device_and_everywhere_ends_it_on_all(): void
    {
        $user = User::factory()->withPassword()->create(['email' => 'ada@example.com']);
        $cookie = $this->recaller($this->postJson('/api/auth/sign-in', ['email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42'])->assertOk());
        $token = $user->refresh()->remember_token;

        // Signing out on one device forgets its cookie, and leaves the others signed in.
        $out = $this->asNewVisit($cookie)->postJson('/api/auth/sign-out')->assertNoContent();
        $this->assertTrue($this->recaller($out)->isCleared());
        $this->assertSame($token, $user->refresh()->remember_token);
        $this->asNewVisit($cookie)->getJson('/api/account')->assertOk();

        // Everywhere: the token changes, so no remember cookie signs anyone in.
        $this->asNewVisit($cookie)->postJson('/api/auth/sign-out-everywhere')->assertNoContent();
        $this->asNewVisit($cookie)->getJson('/api/account')->assertUnauthorized();
    }

    public function test_google_and_the_emailed_link_are_remembered_as_long_as_a_password(): void
    {
        // Hillel, 2026-11-09: "Please make sure all logins are permanmnet with cookies". Every way in is
        // SignInSession::start, which always remembers; Apple's is the same callback as Google's.
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-1', 'name' => 'Ada', 'email' => 'ada@example.com'])->setRaw(['email_verified' => true]));
        $this->post('/auth/google', ['intended' => '/songs'])->assertRedirect();
        $google = $this->recaller($this->get('/auth/google/callback?code=x&state=y')->assertRedirect());
        $this->assertGreaterThan(now()->addYears(4)->getTimestamp(), $google->getExpiresTime());

        Auth::forgetGuards();
        $this->app['session']->driver()->flush();
        $this->postJson('/api/auth/link', ['email' => 'kim@example.com'])->assertStatus(202);
        $mail = Mail::queued(SignInLinkMail::class, fn (SignInLinkMail $m): bool => $m->hasTo('kim@example.com'))->last();
        $this->assertNotNull($mail);
        $link = $this->recaller($this->postJson('/api/auth/link/open', ['token' => $mail->token, 'confirm' => true])->assertOk());
        $this->assertGreaterThan(now()->addYears(4)->getTimestamp(), $link->getExpiresTime());
    }

    public function test_the_admin_areas_own_limits_end_the_admin_area_but_never_the_sign_in(): void
    {
        // Each of the admin session's limits: idle a working day, a week since it began, another browser.
        $admin = null;

        foreach (['idle' => fn () => $this->travel((int) config('admin.idle_timeout') + 1)->minutes(), 'week' => fn () => $this->travel((int) config('admin.max_lifetime') + 1)->minutes(), 'browser' => null] as $limit => $wait) {
            Auth::forgetGuards();
            $this->app['session']->driver()->flush();
            $admin = $this->actingAsAdmin($admin?->refresh());
            $admin->forceFill(['remember_token' => 'kept-'.$limit])->save();
            $value = $admin->id.'|kept-'.$limit.'|'.$this->guard()->hashPasswordForCookie($admin->getAuthPassword());
            $this->withCookie($this->guard()->getRecallerName(), $value)->getJson('/api/admin/me')->assertOk();
            $wait !== null ? $wait() : $this->withHeader('User-Agent', 'Another browser');

            $ended = $this->withCookie($this->guard()->getRecallerName(), $value)->getJson('/api/admin/me');
            $ended->assertUnauthorized();
            // Signed in still, and the remember cookie is never cleared, nor its token changed.
            foreach ($ended->headers->getCookies() as $cookie) {
                $this->assertFalse($cookie->getName() === $this->guard()->getRecallerName() && $cookie->isCleared(), $limit.': the remember cookie was cleared');
            }
            $this->assertAuthenticatedAs($admin);
            $this->assertSame('kept-'.$limit, $admin->refresh()->remember_token);
            $this->getJson('/api/account')->assertOk();
            // The admin area asks for Google and the second factor again: not_admin, which the admin
            // screens take to the admin sign-in (js/admin/auth.js).
            $this->getJson('/api/admin/me')->assertForbidden()->assertJsonPath('code', 'not_admin');
            $this->travelBack();
            $this->flushHeaders();
        }
    }

    public function test_an_apps_sign_in_lasts_while_the_app_is_used_at_least_once_in_400_days(): void
    {
        $user = User::factory()->create();
        $issued = app(AppTokens::class)->issue($user, ['name' => 'Phone', 'platform' => 'ios'], 'password', '127.0.0.1');
        $device = AppDevice::query()->sole();
        $this->assertSame(400, AppTokens::REFRESH_DAYS);
        $this->assertTrue($device->refresh_expires_at->between(now()->addDays(399), now()->addDays(401)));
        $this->assertArrayHasKey('refreshToken', $issued);
    }

    private function recaller(TestResponse $response): Cookie
    {
        $name = $this->guard()->getRecallerName();

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail('No remember cookie was set.');
    }

    /**
     * A later visit: no session and nobody signed in, only the remember cookie.
     */
    private function asNewVisit(Cookie $cookie): self
    {
        Auth::forgetGuards();
        // The test's session store outlives a request; a new visit's starts empty.
        $this->app['session']->driver()->flush();
        $this->flushHeaders();
        $this->defaultCookies = [];

        return $this->withCredentials()->withCookie($cookie->getName(), CookieValuePrefix::remove(decrypt((string) $cookie->getValue(), false)));
    }

    private function guard(): SessionGuard
    {
        $guard = Auth::guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);

        return $guard;
    }
}
