<?php

declare(strict_types=1);

namespace App\Services\DigitalOcean;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Remove files from the edge caches: DigitalOcean's Spaces CDN, and Cloudflare's once public media is behind it
 * (docs/runbook.md, "Media behind Cloudflare"). Public files are cached for a year, so a deleted guide (a singer asked
 * for removal) would otherwise keep playing from the edge. Without their tokens (locally, in tests) it does nothing.
 */
final class CdnPurge
{
    /**
     * @param  list<string>  $keys  object keys on the `media` bucket
     */
    public function forget(array $keys): void
    {
        try {
            $this->forgetOrFail($keys);
        } catch (Throwable $e) {
            // The file is gone from the bucket already; the edge copy expires by itself. Say so.
            report($e);
            Log::warning('CDN purge failed', ['keys' => $keys]);
        }
    }

    /**
     * The same, for a caller that tries again (PurgeCdn): a failure throws.
     *
     * @param  list<string>  $keys
     */
    public function forgetOrFail(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $token = (string) config('services.digitalocean.token');
        $endpoint = (string) config('services.digitalocean.cdn_endpoint_id');

        if ($token !== '' && $endpoint !== '') {
            Http::withToken($token)->timeout(15)
                ->delete("https://api.digitalocean.com/v2/cdn/endpoints/{$endpoint}/cache", ['files' => array_values($keys)])
                ->throw();
        }

        // Media behind Cloudflare (CDN_URL on the proxied host): its cache too, by each file's address, 30 a call.
        $zone = (string) config('services.cloudflare_cdn.zone_id');
        $purge = (string) config('services.cloudflare_cdn.purge_token');
        $base = rtrim((string) config('filesystems.disks.media.url'), '/');

        if ($zone !== '' && $purge !== '' && $base !== '') {
            foreach (array_chunk(array_values($keys), 30) as $chunk) {
                Http::withToken($purge)->timeout(15)
                    ->post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", ['files' => array_map(static fn (string $key): string => $base.'/'.ltrim($key, '/'), $chunk)])
                    ->throw();
            }
        }
    }
}
