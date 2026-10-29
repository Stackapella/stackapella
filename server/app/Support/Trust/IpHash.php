<?php

declare(strict_types=1);

namespace App\Support\Trust;

/**
 * IP addresses are kept raw only in security_events, for 30 days. Everywhere else (the limiters'
 * keys, the audit log, linked accounts) they're an HMAC with the app key, so the cache and the
 * database never hold a raw address (docs/trust-and-safety-plan.md, "Privacy, kept").
 */
final class IpHash
{
    /**
     * The same address always gives the same hash, so an IP can be linked across accounts.
     */
    public static function of(string $ip): string
    {
        return hash_hmac('sha256', 'ip|'.self::subject($ip), (string) config('app.key'));
    }

    /**
     * A hash that changes every day (Event Schedule's pattern), for counts that are per calendar
     * day anyway. Not for the limiters: their counts would start again at midnight
     * (docs/security-review.md), and each key leaves the cache when its window ends anyway.
     */
    public static function daily(string $ip): string
    {
        return hash_hmac('sha256', 'ip|'.now()->toDateString().'|'.self::subject($ip), (string) config('app.key'));
    }

    /**
     * Who an address stands for. An IPv4 address is one subscriber; an IPv6 subscriber usually
     * has a whole /64 and can rotate through it, so IPv6 addresses count by their /64.
     */
    public static function subject(string $ip): string
    {
        $ip = self::canonical($ip);

        if (! str_contains($ip, ':')) {
            return $ip;
        }

        $packed = (string) inet_pton($ip);

        return (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }

    /**
     * One spelling per address (2001:0db8::1 and 2001:db8::1 are the same), or the input as it
     * was when it isn't an address.
     */
    public static function canonical(string $ip): string
    {
        $packed = @inet_pton(trim($ip));

        return $packed === false ? strtolower(trim($ip)) : (string) inet_ntop($packed);
    }

    public static function email(string $email): string
    {
        return hash_hmac('sha256', 'email|'.strtolower(trim($email)), (string) config('app.key'));
    }
}
