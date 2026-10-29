<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * An anonymous API read starts no session (App\Http\Middleware\SessionlessAnonymousReads): a client
 * without cookies no longer writes a session row per request. auth.config still starts one, with
 * the XSRF-TOKEN cookie the app's changes need; a browser with a session goes on as before, and
 * the limits still count.
 */
final class AnonymousReadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // As in production: sessions in the database, so a saved one is a row.
        config(['session.driver' => 'database']);
    }

    /** @return list<string> the cookies the answer sets */
    private static function cookies(TestResponse $response): array
    {
        return array_map(static fn ($cookie): string => $cookie->getName(), $response->headers->getCookies());
    }

    public function test_an_anonymous_read_writes_no_session_and_sets_no_session_cookie(): void
    {
        foreach (['/api/templates', '/api/billing/prices', '/api/explore', '/api/account', '/api/ideas'] as $path) {
            $response = $this->getJson($path);
            $this->assertNotContains((string) config('session.cookie'), self::cookies($response), $path);
            $this->assertNotContains('XSRF-TOKEN', self::cookies($response), $path);
        }

        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_auth_config_starts_the_session_and_gives_the_csrf_cookie(): void
    {
        $config = $this->getJson('/api/auth/config')->assertOk();

        $this->assertContains((string) config('session.cookie'), self::cookies($config));
        $this->assertContains('XSRF-TOKEN', self::cookies($config));
        $this->assertSame(1, DB::table('sessions')->count());
    }

    public function test_a_browser_with_a_session_goes_on_as_before(): void
    {
        $ada = User::factory()->create()->fresh() ?? throw new \LogicException;

        // Signed in by its session: the session is kept and its cookie sent back.
        $this->actingAs($ada)->getJson('/api/account')->assertOk()->assertJsonPath('data.email', $ada->email);

        $this->app['auth']->forgetGuards();
        // (Not a cached public read: those never set cookies, session or not.)
        $read = $this->withCredentials()->withCookie((string) config('session.cookie'), 'an-old-session-id')->getJson('/api/account')->assertUnauthorized();
        $this->assertContains((string) config('session.cookie'), self::cookies($read));
    }

    public function test_the_limits_still_count_anonymous_reads(): void
    {
        config(['limits.api.signed_out.max' => 2]);

        $this->getJson('/api/billing/prices')->assertOk();
        $this->getJson('/api/billing/prices')->assertOk();
        $this->getJson('/api/billing/prices')->assertStatus(429);
        $this->assertSame(0, DB::table('sessions')->count());
    }
}
