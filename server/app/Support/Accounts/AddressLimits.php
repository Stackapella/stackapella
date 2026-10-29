<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Support\Trust\IpHash;
use App\Support\Trust\Limits;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limits on what we send to one address (config/limits.php `verification-email`, and the reset's
 * per-address rule), checked where the answer must look the same either way: a refused send is
 * simply not sent, so nobody learns whether an address has an account. With the sender's IP, the
 * limiter's `ip` rules count too (a resend aimed at many addresses from one network). Admin's
 * overrides apply, as on the route limiters.
 */
final class AddressLimits
{
    public function __construct(private readonly Limits $limits) {}

    public function allows(string $limiter, string $email, ?string $ip = null): bool
    {
        $periods = ['minute' => 60, 'hour' => 3600, 'day' => 86400];
        $keys = [];

        foreach ($this->limits->all()[$limiter] ?? [] as $name => $rule) {
            $by = match (true) {
                $rule['by'] === 'email' => IpHash::email($email),
                $rule['by'] === 'ip' && $ip !== null => IpHash::of($ip),
                default => null,
            };

            if ($by === null) {
                continue;
            }

            $key = "{$limiter}:{$name}:{$by}";

            if (RateLimiter::tooManyAttempts($key, $rule['max'])) {
                return false;
            }

            $keys[$key] = $periods[$rule['per']];
        }

        foreach ($keys as $key => $seconds) {
            RateLimiter::hit($key, $seconds);
        }

        return true;
    }
}
