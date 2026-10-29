<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Whether a visitor came from mudeo (docs/decisions.md, "only people who come from mudeo see any
 * mudeo info"): a ?ref=mudeo link, or the early-access or preview link, remembered for the visit
 * by a session cookie both hosts read (config hosts.pass_domain, as the beta pass). Only they see
 * the landing's mudeo letter and banner. The cookie holds no person: just "1".
 */
final class MudeoArrival
{
    public const COOKIE = 'sa_mudeo';

    public const REF = 'mudeo';

    /** Set once someone from mudeo has started singing, so the count takes them once (it too holds just "1"). */
    public const USED_COOKIE = 'sa_mudeo_used';

    public static function is(Request $request, ?string $ref): bool
    {
        return $ref === self::REF || $request->cookies->get(self::COOKIE) === '1';
    }

    /**
     * Ends with the browser session; unencrypted (bootstrap/app.php), as there's nothing in it.
     */
    public static function cookie(Request $request, string $name = self::COOKIE): Cookie
    {
        $domain = config('hosts.pass_domain');

        return Cookie::create($name, '1', 0, '/', is_string($domain) && $domain !== '' ? $domain : null, $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }
}
