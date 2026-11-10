<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Http\Middleware\SignedInHint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * The public site's "Signed in?" (docs/tools-plan.md): one cookie, sa_signed_in=1, on the domain the
 * two hosts share, readable by the site's script, holding no ID; set on a signed-in visit to the
 * app's host and cleared once a session there holds no one.
 */
final class SignedInHintTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'hosts.pass_domain' => '.stackapella.test']);
        // A browser sends its cookies with the app's fetches.
        $this->withCredentials();
    }

    private function hint(TestResponse $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === SignedInHint::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }

    public function test_a_signed_in_visit_sets_a_plain_shared_cookie_and_a_sign_out_clears_it(): void
    {
        $this->actingAs(User::factory()->create()->refresh());
        $cookie = $this->hint($this->getJson(self::APP.'/api/account')->assertOk());

        // Only "1", unencrypted (the site's script reads it), on the shared domain, never HttpOnly.
        $this->assertNotNull($cookie);
        $this->assertSame('1', $cookie->getValue());
        $this->assertSame('.stackapella.test', $cookie->getDomain());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addDays(300)->getTimestamp(), $cookie->getExpiresTime());

        // Already there: not set again on every answer.
        $this->assertNull($this->hint($this->withUnencryptedCookie(SignedInHint::COOKIE, '1')->getJson(self::APP.'/api/account')));

        // Signing out ends it.
        $out = $this->hint($this->withUnencryptedCookie(SignedInHint::COOKIE, '1')->postJson(self::APP.'/api/auth/sign-out'));
        $this->assertNotNull($out);
        $this->assertTrue($out->isCleared());
        $this->assertSame('.stackapella.test', $out->getDomain());
    }

    public function test_an_ended_session_clears_it_and_a_read_with_no_session_leaves_it(): void
    {
        // Nobody signed in and no hint: nothing is written.
        $this->assertNull($this->hint($this->getJson(self::APP.'/api/auth/config')));

        // An anonymous read that starts no session can't tell: the hint stays.
        $this->assertNull($this->hint($this->withUnencryptedCookie(SignedInHint::COOKIE, '1')->getJson(self::APP.'/api/templates')->assertOk()));

        // Signed out elsewhere or a session that ended: the app's first call (auth.config) clears it.
        $cleared = $this->hint($this->getJson(self::APP.'/api/auth/config')->assertOk());
        $this->assertTrue($cleared?->isCleared());
    }

    public function test_never_on_the_public_host_or_for_the_native_apps(): void
    {
        $this->actingAs(User::factory()->create()->refresh());

        $this->assertNull($this->hint($this->get(self::SITE.'/pricing')->assertOk()));
        $this->assertNull($this->hint($this->withHeader('Authorization', 'Bearer not-a-real-token')->getJson(self::APP.'/api/templates')));
    }
}
