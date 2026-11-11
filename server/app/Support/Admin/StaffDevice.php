<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * A device an admin has used (Hillel: "make sure my admin visits aren't counted in analytics"):
 * set when an admin passes the second factor, on the domain both hosts share, and kept when they
 * sign out (it's the device, not the session). The anonymous counts ignore any request carrying it.
 * Its value is only "1": no ID, nothing about anyone.
 */
final class StaffDevice
{
    public const COOKIE = 'sa_staff';

    public static function is(Request $request): bool
    {
        return $request->cookies->get(self::COOKIE) === '1';
    }

    /** A language not yet open, previewed on this device (SiteLanguage): an admin's, and unforgeable. */
    public const PREVIEW_COOKIE = 'sa_preview';

    /**
     * Whether this device may preview what's not yet open to everyone (a language before its switch): an admin's,
     * by a cookie only the server can make (an HMAC of the app's key), unlike COOKIE's plain "1".
     */
    public static function previews(Request $request): bool
    {
        $value = $request->cookies->get(self::PREVIEW_COOKIE);

        return is_string($value) && hash_equals(self::previewValue(), $value);
    }

    public static function previewCookie(Request $request): Cookie
    {
        $domain = config('hosts.pass_domain');

        return Cookie::create(self::PREVIEW_COOKIE, self::previewValue(), now()->addDays(30), '/', is_string($domain) && $domain !== '' ? $domain : null, $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }

    private static function previewValue(): string
    {
        return hash_hmac('sha256', 'staff-preview', (string) config('app.key'));
    }

    /** A year; unencrypted (bootstrap/app.php), as there's nothing in it. */
    public static function cookie(Request $request): Cookie
    {
        $domain = config('hosts.pass_domain');

        return Cookie::create(self::COOKIE, '1', now()->addYear(), '/', is_string($domain) && $domain !== '' ? $domain : null, $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }
}
