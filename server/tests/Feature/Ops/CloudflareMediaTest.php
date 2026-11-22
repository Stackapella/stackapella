<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Services\DigitalOcean\CdnPurge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Media behind Cloudflare (docs/runbook.md): nothing changes until it's flipped; once CDN_URL is the proxied host
 * and the zone's purge token is set, a deleted file is purged from Cloudflare's cache by its address, as from
 * DigitalOcean's, and the pages allow the new host for media.
 */
final class CloudflareMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_unflipped_only_digitaloceans_cdn_is_purged(): void
    {
        Http::fake();
        config(['services.digitalocean.token' => 'do-token', 'services.digitalocean.cdn_endpoint_id' => 'endpoint-1', 'services.cloudflare_cdn.zone_id' => null]);

        app(CdnPurge::class)->forgetOrFail(['h/abc.m4a']);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'api.digitalocean.com/v2/cdn/endpoints/endpoint-1/cache'));
    }

    public function test_flipped_cloudflare_is_purged_by_each_files_address_thirty_at_a_time(): void
    {
        Http::fake();
        config([
            'services.digitalocean.token' => null,
            'services.cloudflare_cdn.zone_id' => 'zone-1',
            'services.cloudflare_cdn.purge_token' => 'cf-purge',
            'filesystems.disks.media.url' => 'https://media.example.com',
        ]);
        $keys = array_map(static fn (int $i): string => "h/file{$i}.m4a", range(1, 31));

        app(CdnPurge::class)->forgetOrFail($keys);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-1/purge_cache'
            && $request->hasHeader('Authorization', 'Bearer cf-purge')
            && ($request->data()['files'][0] ?? null) === 'https://media.example.com/h/file1.m4a'
            && count($request->data()['files']) === 30);
    }

    public function test_the_pages_allow_whatever_host_media_is_served_from(): void
    {
        config(['filesystems.disks.media.url' => 'https://media.example.com']);

        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('#media-src[^;]*https://media\.example\.com#', $csp);
        $this->assertMatchesRegularExpression('#img-src[^;]*https://media\.example\.com#', $csp);
    }
}
