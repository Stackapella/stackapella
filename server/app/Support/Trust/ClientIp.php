<?php

declare(strict_types=1);

namespace App\Support\Trust;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The client's real address behind Cloudflare and App Platform's load balancer
 * (config/proxies.php). Without this, $request->ip() would be a proxy's, and every visitor would
 * share one limit.
 */
final class ClientIp
{
    /**
     * @param  string  $socketAddress  the address that connected to PHP (REMOTE_ADDR)
     * @param  string|null  $connectingIp  App Platform's do-connecting-ip header
     * @param  string|null  $cloudflareIp  the CF-Connecting-IP header
     */
    public static function resolve(string $socketAddress, ?string $connectingIp, ?string $cloudflareIp): string
    {
        $socketAddress = IpHash::canonical($socketAddress);

        if (! config('proxies.behind_app_platform')) {
            return $socketAddress;
        }

        $connecting = self::valid($connectingIp);

        // Only App Platform's load balancer sets do-connecting-ip. Without it, the request didn't
        // come through the load balancer, so nothing it says about the client can be believed.
        if ($connecting === null) {
            return $socketAddress;
        }

        /** @var list<string> $ranges */
        $ranges = (array) config('proxies.cloudflare');
        $cloudflare = self::valid($cloudflareIp);

        if ($cloudflare !== null && IpUtils::checkIp($connecting, $ranges)) {
            return $cloudflare;
        }

        return $connecting;
    }

    private static function valid(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? IpHash::canonical($ip) : null;
    }
}
