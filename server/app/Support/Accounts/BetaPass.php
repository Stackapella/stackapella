<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Before launch, the app opens only to people let in on purpose (WebAppController's coming-soon
 * gate): a long-lived signed cookie, given by an open beta invite, the mudeo email's early-access
 * link, Hillel's preview link, or signing in (accounts only exist by invitation while sign-ups are
 * paused). An ordinary session cookie, which any API call sets, no longer opens it.
 */
final class BetaPass
{
    public const COOKIE = 'sa_pass';

    public const DAYS = 400;

    /** The apps' pass, sent as a header (they keep no cookies). */
    public const HEADER = 'X-App-Pass';

    public static function valid(Request $request): bool
    {
        $header = $request->headers->get(self::HEADER);
        $value = is_string($header) && str_starts_with($header, 'sap_') ? substr($header, 4) : $request->cookies->get(self::COOKIE);

        if (! is_string($value) || preg_match('/^(\d{10})\.([0-9a-f]{64})$/', $value, $match) !== 1) {
            return false;
        }

        return (int) $match[1] > time() && hash_equals(self::sign($match[1]), $match[2]);
    }

    public static function cookie(Request $request): Cookie
    {
        $until = (string) now()->addDays(self::DAYS)->getTimestamp();

        // Read on both hosts before launch (config hosts.pass_domain, ".stackapella.com").
        $domain = config('hosts.pass_domain');

        return Cookie::create(self::COOKIE, $until.'.'.self::sign($until), (int) $until, '/', is_string($domain) && $domain !== '' ? $domain : null, $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }

    /**
     * appPass.claim: the same signed expiry as the cookie, for an app to send as X-App-Pass.
     */
    public static function appToken(): string
    {
        $until = (string) now()->addDays(self::DAYS)->getTimestamp();

        return 'sap_'.$until.'.'.self::sign($until);
    }

    private static function sign(string $until): string
    {
        return hash_hmac('sha256', 'beta-pass|'.$until, (string) config('app.key'));
    }
}
