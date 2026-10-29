<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Whether an address someone gave us (a selfhosted Event Schedule) is safe for our server to call,
 * and where to connect (after iaw's SafeFetcher): https on a usual port, a real host name, and every
 * address it resolves to on the public internet. Never one inside a private network, the cloud's
 * metadata service, the server itself, or the IPv6 forms that reach those. The caller pins the
 * connection to the address checked here (CURLOPT_RESOLVE), so DNS can't swap it afterwards.
 * Locally (`local`) http and localhost are allowed, to try a local install.
 */
final class PublicAddress
{
    public const PORTS = [443, 8443];

    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/96', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/32', '2001:db8::/32',
        '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /** @var (callable(string): list<string>)|null How a host is looked up (the tests give their own). */
    public static $resolver = null;

    public static function allowed(string $url): bool
    {
        return self::resolve($url) !== false;
    }

    /**
     * Where to connect: the host, its port and the one checked IP to pin it to (null locally,
     * where nothing is pinned); false when the address isn't allowed.
     *
     * @return array{host: string, port: int, ip: string|null}|false
     */
    public static function resolve(string $url): array|false
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (app()->environment('local') && in_array($scheme, ['http', 'https'], true)) {
            return ['host' => $host, 'port' => $port, 'ip' => null];
        }

        if ($scheme !== 'https' || ! in_array($port, self::PORTS, true) || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            return false;
        }

        $ips = self::$resolver !== null ? (self::$resolver)($host) : self::lookup($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return false;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $address = (string) inet_pton($ip);

        foreach (self::BLOCKED_RANGES as $range) {
            [$network, $bits] = explode('/', $range);
            $network = (string) inet_pton($network);

            if (strlen($network) !== strlen($address)) {
                continue;
            }

            $bits = (int) $bits;
            $bytes = intdiv($bits, 8);
            $remainder = $bits % 8;
            $mask = (0xFF << (8 - $remainder)) & 0xFF;

            if (strncmp($address, $network, $bytes) === 0 && ($remainder === 0 || ((ord($address[$bytes]) ^ ord($network[$bytes])) & $mask) === 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function lookup(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return is_array($records) ? array_values(array_filter(array_map(static fn (array $r): ?string => $r['ip'] ?? $r['ipv6'] ?? null, $records))) : [];
    }
}
