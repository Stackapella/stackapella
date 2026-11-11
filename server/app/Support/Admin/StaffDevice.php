<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\AdminSecondFactor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /** How long a preview cookie holds, checked by the server from its own signed expiry (not the browser's). */
    private const PREVIEW_DAYS = 30;

    /**
     * Whether this device may preview what's not yet open to everyone (a language before its switch): an admin's,
     * by a cookie only the server can make, unlike COOKIE's plain "1". Its value is "<expiry>.<signature>": the
     * signature (an HMAC of the app's key) covers the expiry and who may preview now (previewers()), so a copied
     * value stops at its expiry, and stops at once for everyone when an admin is removed or suspended or a second
     * factor is removed. Admins get a new one when they next pass the second factor. No ID is in it.
     */
    public static function previews(Request $request): bool
    {
        $value = $request->cookies->get(self::PREVIEW_COOKIE);
        if (! is_string($value) || preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/D', $value, $parts) !== 1 || (int) $parts[1] < now()->getTimestamp()) {
            return false;
        }

        return hash_equals(self::previewSignature((int) $parts[1]), $parts[2]);
    }

    public static function previewCookie(Request $request): Cookie
    {
        $domain = config('hosts.pass_domain');
        $expires = now()->addDays(self::PREVIEW_DAYS);

        return Cookie::create(self::PREVIEW_COOKIE, $expires->getTimestamp().'.'.self::previewSignature($expires->getTimestamp()), $expires, '/', is_string($domain) && $domain !== '' ? $domain : null, $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }

    private static function previewSignature(int $expires): string
    {
        return hash_hmac('sha256', 'staff-preview|'.$expires.'|'.self::previewers(), (string) config('app.key'));
    }

    /**
     * Who may preview now, as one fingerprint: each admin who isn't suspended, with their second factors (each
     * passkey, and when the TOTP backup was confirmed). Any change to it turns every preview cookie into a stranger's.
     */
    private static function previewers(): string
    {
        $admins = User::query()->where('is_admin', true)->whereNull('suspended_at')->orderBy('id')->pluck('id')->all();
        $passkeys = $admins === [] ? [] : DB::table('passkeys')->whereIn('user_id', $admins)->orderBy('id')->pluck('id')->all();
        $factors = $admins === [] ? [] : AdminSecondFactor::query()->whereIn('user_id', $admins)->orderBy('id')->get(['id', 'user_id', 'totp_confirmed_at'])
            ->map(static fn (AdminSecondFactor $f): string => $f->getKey().':'.$f->user_id.':'.$f->totp_confirmed_at?->getTimestamp())->all();

        return hash('sha256', (string) json_encode([$admins, $passkeys, $factors]));
    }

    /** A year; unencrypted (bootstrap/app.php), as there's nothing in it. */
    public static function cookie(Request $request): Cookie
    {
        $domain = config('hosts.pass_domain');

        return Cookie::create(self::COOKIE, '1', now()->addYear(), '/', is_string($domain) && $domain !== '' ? $domain : null, $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }
}
