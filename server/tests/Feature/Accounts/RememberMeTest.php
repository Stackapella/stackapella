<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
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
