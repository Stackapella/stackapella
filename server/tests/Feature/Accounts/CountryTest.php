<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The country an account last signed in from: Cloudflare's CF-IPCountry, believed only through
 * Cloudflare, the latest only, shown to the admin as a field and a count (Hillel's ask).
 */
final class CountryTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct horse battery';

    /**
     * @param  array<string, string>  $headers
     */
    private function signIn(User $user, array $headers): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withHeaders($headers)->postJson('/api/auth/sign-in', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
    }

    public function test_signing_in_through_cloudflare_keeps_the_latest_country_and_nothing_else_is_believed(): void
    {
        config(['proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true]);
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()]);
        $cloudflare = ['do-connecting-ip' => '172.70.1.2', 'cf-connecting-ip' => '198.51.100.7'];

        $this->signIn($user, [...$cloudflare, 'cf-ipcountry' => 'gb']);
        $this->assertSame('GB', $user->refresh()->country);

        // Unknown, Tor, or a header on a request that didn't come through App Platform's edge: kept.
        $this->signIn($user, [...$cloudflare, 'cf-ipcountry' => 'XX']);
        $this->signIn($user, [...$cloudflare, 'cf-ipcountry' => 'T1']);
        $this->signIn($user, ['cf-ipcountry' => 'FR']);
        $this->assertSame('GB', $user->refresh()->country);

        // Straight to App Platform (beta's host, not through our own zone): its edge is Cloudflare's.
        $this->signIn($user, ['do-connecting-ip' => '203.0.113.5', 'cf-ipcountry' => 'NL']);
        $this->assertSame('NL', $user->refresh()->country);

        $this->signIn($user, [...$cloudflare, 'cf-ipcountry' => 'DE']);
        $this->assertSame('DE', $user->refresh()->country);

        // A self-hosted install without Cloudflare never believes it.
        config(['selfhost.behind_cloudflare' => false]);
        $this->signIn($user, [...$cloudflare, 'cf-ipcountry' => 'US']);
        $this->assertSame('DE', $user->refresh()->country);
    }

    public function test_the_admin_sees_each_persons_country_filters_by_it_and_counts_them(): void
    {
        User::factory()->count(2)->create(['country' => 'US']);
        User::factory()->create(['country' => 'GB']);
        $this->actingAsAdmin();

        $counts = $this->getJson('/api/admin/people/countries');
        $this->assertMatchesContract($counts, 'admin.people.countries', 200);
        $this->assertSame(['country' => 'US', 'people' => 2], $counts->json('data.0'));

        $gb = $this->getJson('/api/admin/people?country=gb');
        $this->assertMatchesContract($gb, 'admin.people.list', 200);
        $gb->assertJsonCount(1, 'data')->assertJsonPath('data.0.country', 'GB');
    }

    public function test_an_account_signed_in_before_gets_its_country_once_a_day_and_the_admin_can_see_why(): void
    {
        config(['proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true]);
        $user = User::factory()->create()->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        $this->withHeaders(['do-connecting-ip' => '203.0.113.5', 'cf-ipcountry' => 'IE'])->getJson('/api/auth/config')->assertOk();
        $this->assertSame('IE', $user->refresh()->country);
        // At most once a day.
        $this->withHeaders(['do-connecting-ip' => '203.0.113.5', 'cf-ipcountry' => 'FR'])->getJson('/api/auth/config')->assertOk();
        $this->assertSame('IE', $user->refresh()->country);

        // The admin's one click: what arrived and what the app made of it, never the address.
        $this->flushHeaders();
        $this->actingAsAdmin();
        $seen = $this->withHeaders(['do-connecting-ip' => '203.0.113.5', 'cf-ipcountry' => 'IE'])->getJson('/api/admin/diagnostics/country');
        $this->assertMatchesContract($seen, 'admin.diagnostics.country', 200);
        $seen->assertJsonPath('data.header', 'IE')->assertJsonPath('data.viaAppPlatform', true)->assertJsonPath('data.connectingFromCloudflare', false)->assertJsonPath('data.trusted', true);
        $this->assertStringNotContainsString('203.0.113.5', (string) $seen->getContent());
    }
}
