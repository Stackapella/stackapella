<?php

declare(strict_types=1);

namespace Tests\Feature\Trust;

use App\Enums\SecurityEventType;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Trust\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class LimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refusal_is_a_429_with_the_code_and_when_to_try_again(): void
    {
        config(['limits.api.signed_out.max' => 1]);

        $this->getJson('/api/admin/me')->assertStatus(401);
        $response = $this->getJson('/api/admin/me');

        $this->assertMatchesContract($response, 'admin.me', 429);
        $response->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
        $this->assertGreaterThan(0, $response->json('retryAfter'));
        $this->assertSame((int) $response->headers->get('Retry-After'), $response->json('retryAfter'));
    }

    public function test_a_networks_count_carries_on_past_midnight(): void
    {
        config(['limits.api.signed_out.max' => 2]);
        $this->travelTo(now()->startOfDay()->addDay()->subSeconds(20));

        $this->getJson('/api/billing/prices')->assertOk();
        $this->getJson('/api/billing/prices')->assertOk();
        $this->travel(30)->seconds();

        // Still the same minute's window, though the date has changed.
        $this->getJson('/api/billing/prices')->assertStatus(429);
    }

    public function test_a_refusal_is_recorded_once_per_limiter_and_person(): void
    {
        config(['limits.api.signed_out.max' => 1]);

        foreach (range(1, 4) as $i) {
            $this->getJson('/api/admin/me');
        }

        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::RateLimited)->count());
        $this->assertEquals(['limiter' => 'api', 'rule' => 'signed_out'], SecurityEvent::query()->firstOrFail()->context);
    }

    public function test_signed_in_people_have_their_own_higher_limit(): void
    {
        config(['limits.api.signed_out.max' => 1]);
        $this->actingAsAdmin();

        foreach (range(1, 3) as $i) {
            $this->getJson('/api/admin/me')->assertOk();
        }
    }

    public function test_a_live_override_changes_a_limit_and_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $limits = app(Limits::class);

        $limits->override('api', 'signed_out', 1, $admin, 'An attack');
        $this->assertSame(1, $limits->all()['api']['signed_out']['max']);
        $this->assertSame(120, $limits->all()['api']['signed_out']['default']);

        $this->getJson('/api/admin/me')->assertStatus(401);
        $this->getJson('/api/admin/me')->assertStatus(429);

        $limits->override('api', 'signed_out', null, $admin, 'Over');
        $this->assertNull($limits->all()['api']['signed_out']['override']);
        $this->assertSame(2, AuditLog::query()->where('action', 'limit.limits.api.signed_out')->count());
    }

    public function test_web_pages_get_a_kind_page_not_json(): void
    {
        config(['limits.provider-sign-in.ip.max' => 1]);

        $this->post('/auth/google')->assertRedirect();
        $this->post('/auth/google')->assertStatus(429)->assertSee(__('errors.rate_limited.title'));
    }

    public function test_a_public_page_turned_away_is_a_429_a_search_engine_never_indexes(): void
    {
        // Indexing on, as in production: the refusal must still say noindex, never read as the page.
        config(['ops.indexing' => true, 'limits.public-pages.ip.max' => 1]);

        $this->get('/pricing')->assertOk()->assertDontSee('<meta name="robots" content="noindex">', false);
        $refused = $this->get('/pricing')->assertStatus(429)->assertSee(__('errors.rate_limited.title'));

        $this->assertNotNull($refused->headers->get('Retry-After'));
        $this->assertSame('noindex', $refused->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string) $refused->getContent());
    }

    public function test_an_array_where_an_email_should_be_is_counted_not_a_server_error(): void
    {
        config(['limits.api.signed_out.max' => 1]);

        $this->getJson('/api/admin/me?email[]=x')->assertStatus(401);
        $this->getJson('/api/admin/me?email[]=x')->assertStatus(429);

        config(['limits.sign-in.ip_and_email.max' => 1]);
        $this->assertNotEmpty(app(Limits::class)->for('sign-in', Request::create('/x', 'POST', ['email' => ['a' => 'b']])));
    }

    public function test_ipv6_addresses_in_one_64_share_a_limit(): void
    {
        config(['limits.api.signed_out.max' => 1]);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::1'])->getJson('/api/admin/me')->assertStatus(401);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:ffff::9'])->getJson('/api/admin/me')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])->getJson('/api/admin/me')->assertStatus(401);
    }

    public function test_the_health_check_has_a_generous_limit_of_its_own(): void
    {
        config(['limits.health.ip.max' => 2]);

        $this->get('/up')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/up')->assertStatus(429);
    }

    public function test_every_limiter_in_the_config_is_registered(): void
    {
        foreach (app(Limits::class)->all() as $name => $rules) {
            $this->assertNotEmpty($rules, $name);
            // A signed-out request gets every rule that isn't only for the signed in or a trust level.
            $forAnyone = array_filter($rules, static fn (array $rule): bool => in_array($rule['when'], [null, 'signed_out'], true));
            $this->assertCount(count($forAnyone), app(Limits::class)->for($name, request()), $name);
        }
    }

    /**
     * config/limits.php's public-pages limit as it reads in another environment.
     */
    private function publicPagesMax(string $environment, ?string $limit): int
    {
        $saved = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null, getenv('APP_ENV'), getenv('PUBLIC_PAGES_LIMIT')];
        $set = static function (string $key, ?string $value): void {
            if ($value === null) {
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
            } else {
                $_SERVER[$key] = $_ENV[$key] = $value;
                putenv($key.'='.$value);
            }
        };

        try {
            $set('APP_ENV', $environment);
            $set('PUBLIC_PAGES_LIMIT', $limit);

            return (int) (require config_path('limits.php'))['public-pages']['ip']['max'];
        } finally {
            $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $saved[0] ?? 'testing';
            putenv('APP_ENV='.($saved[2] !== false ? $saved[2] : 'testing'));
            $set('PUBLIC_PAGES_LIMIT', $saved[3] !== false ? $saved[3] : null);
        }
    }

    public function test_the_public_pages_limit_is_raised_only_for_local_and_testing(): void
    {
        // Production keeps 600 a minute whatever the env says: above a search engine's crawl, a scraper's wall.
        $this->assertSame(600, $this->publicPagesMax('production', null));
        $this->assertSame(600, $this->publicPagesMax('production', '9999'));
        $this->assertSame(600, $this->publicPagesMax('staging', '9999'));
        // On a developer's Mac every agent's flows share 127.0.0.1.
        $this->assertSame(2000, $this->publicPagesMax('testing', null));
        $this->assertSame(5000, $this->publicPagesMax('local', '5000'));
        $this->assertSame('testing', app()->environment());
    }
}
