<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Exceptions\Refusal;
use App\Support\Trust\IpHash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Wrong passwords (docs/trust-and-safety-plan.md, "Accounts and admin, hardened"), counted twice:
 * per address and network, 10 in 15 minutes, then that network waits; and per address whatever
 * the network, 50 in an hour, so guessing from many networks still stops. One network can't keep
 * the owner out by failing on purpose (docs/security-review.md): the owner's own network has its
 * own count. Counted the same for an address with no account, so the wait never tells whether one
 * exists; the rate limiter's increment is atomic, so a burst can't slip past it. Signing in and
 * re-entering the password share it.
 */
final class PasswordFailures
{
    public const MAX = 10;

    public const MINUTES = 15;

    /** Wrong passwords for one address from every network together, in an hour. */
    public const MAX_EVERYWHERE = 50;

    public function ensureAllowed(string $email, ?string $ip): void
    {
        foreach ([[self::networkKey($email, $ip), self::MAX], [self::addressKey($email), self::MAX_EVERYWHERE]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = max(1, RateLimiter::availableIn($key));

                throw new Refusal(429, 'rate_limited', ['retryAfter' => $wait], ['Retry-After' => (string) $wait]);
            }
        }
    }

    public function failed(string $email, ?string $ip): void
    {
        RateLimiter::hit(self::networkKey($email, $ip), self::MINUTES * 60);
        RateLimiter::hit(self::addressKey($email), 3600);
    }

    /** The right password: this network's count starts again (other networks' stay). */
    public function clear(string $email, ?string $ip): void
    {
        RateLimiter::clear(self::networkKey($email, $ip));
    }

    /** A new password set from the inbox: the count across networks starts again. */
    public function clearEverywhere(string $email): void
    {
        RateLimiter::clear(self::addressKey($email));
    }

    private static function networkKey(string $email, ?string $ip): string
    {
        return self::addressKey($email).':'.IpHash::of((string) $ip);
    }

    private static function addressKey(string $email): string
    {
        return 'password-failures:'.IpHash::email($email);
    }
}
