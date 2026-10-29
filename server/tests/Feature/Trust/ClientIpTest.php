<?php

declare(strict_types=1);

namespace Tests\Feature\Trust;

use App\Support\Trust\ClientIp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real client behind Cloudflare and App Platform (docs/trust-and-safety-plan.md, "The limits").
 */
final class ClientIpTest extends TestCase
{
    use RefreshDatabase;

    public function test_locally_the_socket_address_is_the_client_and_headers_are_ignored(): void
    {
        config(['proxies.behind_app_platform' => false]);

        $this->assertSame('10.0.0.9', ClientIp::resolve('10.0.0.9', '203.0.113.5', '198.51.100.7'));
    }

    public function test_behind_cloudflare_the_client_is_cf_connecting_ip(): void
    {
        config(['proxies.behind_app_platform' => true]);

        $this->assertSame('198.51.100.7', ClientIp::resolve('10.0.0.9', '172.70.1.2', '198.51.100.7'));
        $this->assertSame('2001:db8::1', ClientIp::resolve('10.0.0.9', '2606:4700::1', '2001:db8::1'));
    }

    public function test_a_request_that_skipped_cloudflare_cannot_forge_cf_connecting_ip(): void
    {
        config(['proxies.behind_app_platform' => true]);

        $this->assertSame('203.0.113.5', ClientIp::resolve('10.0.0.9', '203.0.113.5', '198.51.100.7'));
    }

    public function test_without_the_load_balancers_header_nothing_is_believed(): void
    {
        config(['proxies.behind_app_platform' => true]);

        $this->assertSame('10.0.0.9', ClientIp::resolve('10.0.0.9', null, '198.51.100.7'));
        $this->assertSame('10.0.0.9', ClientIp::resolve('10.0.0.9', 'not an ip', '198.51.100.7'));
    }

    public function test_the_limiters_key_off_the_real_client(): void
    {
        config(['proxies.behind_app_platform' => true, 'limits.api.signed_out.max' => 2]);
        $from = fn (string $client): array => ['HTTP_DO_CONNECTING_IP' => '172.70.1.2', 'HTTP_CF_CONNECTING_IP' => $client, 'HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'HTTP_ACCEPT' => 'application/json'];

        // Two visitors through the same Cloudflare edge each get their own limit.
        foreach (range(1, 2) as $i) {
            $this->call('GET', '/api/admin/me', [], [], [], $from('198.51.100.7'))->assertStatus(401);
        }
        $this->call('GET', '/api/admin/me', [], [], [], $from('198.51.100.7'))->assertStatus(429);
        $this->call('GET', '/api/admin/me', [], [], [], $from('198.51.100.8'))->assertStatus(401);
    }

    public function test_the_apps_own_platform_address_redirects_to_the_real_domain(): void
    {
        config(['proxies.behind_app_platform' => true]);

        $this->get('https://stackapella-abc12.ondigitalocean.app/admin/sign-in')->assertStatus(302)->assertRedirect('https://stackapella.test/admin/sign-in');
        $this->get('https://stackapella-abc12.ondigitalocean.app/up')->assertOk();
    }
}
