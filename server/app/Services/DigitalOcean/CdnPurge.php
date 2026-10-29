<?php

declare(strict_types=1);

namespace App\Services\DigitalOcean;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Remove files from the Spaces CDN's edge caches. Public files are cached for a year, so a
 * deleted guide (a singer asked for removal) would otherwise keep playing from the edge. Without
 * a DigitalOcean token and the CDN endpoint (locally, in tests) it does nothing.
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
        $token = (string) config('services.digitalocean.token');
        $endpoint = (string) config('services.digitalocean.cdn_endpoint_id');

        if ($keys === [] || $token === '' || $endpoint === '') {
            return;
        }

        Http::withToken($token)->timeout(15)
            ->delete("https://api.digitalocean.com/v2/cdn/endpoints/{$endpoint}/cache", ['files' => array_values($keys)])
            ->throw();
    }
}
