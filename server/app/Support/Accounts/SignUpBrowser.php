<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The browser or app that signed up (docs/decisions.md, 2026-11-01): a secret it holds (the web, an
 * HttpOnly cookie; an app, its own `signupKey`), whose hash the confirm-your-email link carries.
 * The link keeps the sign-up's password only when the same secret comes back with it.
 */
final class SignUpBrowser
{
    public const COOKIE = 'sa_signup';

    /** The secret this request brings back (an app's key, else the cookie), as its hash. */
    public static function hash(Request $request): ?string
    {
        $key = $request->input('signupKey');
        $secret = is_string($key) && $key !== '' ? $key : $request->cookie(self::COOKIE);

        return is_string($secret) && $secret !== '' ? hash('sha256', $secret) : null;
    }

    /**
     * A sign-up's secret: the app's key when it sent one, else a new one for the cookie.
     *
     * @return array{0: string, 1: Cookie|null} the hash, and the cookie to set (web only)
     */
    public static function start(Request $request): array
    {
        $key = $request->input('signupKey');

        if (is_string($key) && $key !== '') {
            return [hash('sha256', $key), null];
        }

        $secret = Str::random(48);

        // Two days: the link lasts a day, and a resend from the same browser carries it on.
        return [hash('sha256', $secret), cookie(self::COOKIE, $secret, 60 * 48, '/', null, $request->isSecure() || app()->isProduction(), true, false, 'lax')];
    }
}
