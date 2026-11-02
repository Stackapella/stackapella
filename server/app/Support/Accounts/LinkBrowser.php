<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The browser that asked for a sign-in link (Hillel: "Yes, add the confirm screen", 2026-11-01): an
 * HttpOnly cookie whose hash the link keeps, so opening it anywhere else asks "Sign in as x@y?"
 * first, and nobody can be quietly signed into someone else's account by a link sent to them.
 */
final class LinkBrowser
{
    public const COOKIE = 'sa_link';

    /** @return array{0: string, 1: Cookie} */
    public static function start(Request $request): array
    {
        $secret = Str::random(48);

        // An hour: a link lasts 15 minutes.
        return [hash('sha256', $secret), cookie(self::COOKIE, $secret, 60, '/', null, $request->isSecure() || app()->isProduction(), true, false, 'lax')];
    }

    public static function hash(Request $request): ?string
    {
        $secret = $request->cookie(self::COOKIE);

        return is_string($secret) && $secret !== '' ? hash('sha256', $secret) : null;
    }
}
