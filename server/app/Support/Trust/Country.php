<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The country a request comes from, as Cloudflare names it (CF-IPCountry), believed only when the
 * request came through App Platform's edge (which is Cloudflare's): never from a database of
 * addresses, never stored with one. Kept on the account as the latest country it signed in from.
 */
final class Country
{
    public static function of(Request $request): ?string
    {
        if (config('selfhost.behind_cloudflare') !== true || ! config('proxies.behind_app_platform')) {
            return null;
        }

        // Only App Platform's edge sets do-connecting-ip, and that edge is Cloudflare's: it sets
        // CF-IPCountry whether the visitor came through our own Cloudflare zone (the connecting IP
        // is then one of Cloudflare's) or straight to App Platform (beta's host: the visitor's
        // own). A request without it didn't come through the edge, so its header is just a header.
        // What's left to forge is someone's own country, through the edge, on their own account.
        $connecting = trim((string) $request->headers->get('do-connecting-ip'));

        if (filter_var($connecting, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $country = strtoupper(trim((string) $request->headers->get('cf-ipcountry')));

        // XX: unknown; T1: Tor.
        return preg_match('/^[A-Z]{2}$/', $country) === 1 && ! in_array($country, ['XX', 'T1'], true) ? $country : null;
    }

    /**
     * Once a day on a signed-in request the app makes anyway (auth.config): accounts signed in
     * before countries were kept get one too, and it follows them as they move.
     */
    public static function daily(User $user, Request $request): void
    {
        if (Cache::add('country-checked:'.$user->id.':'.now()->toDateString(), true, now()->endOfDay())) {
            self::remember($user, $request);
        }
    }

    /**
     * For the admin's diagnostic (admin.diagnostics.country): what this request carried and what
     * the app made of it. Booleans and the two letters only, never an address.
     *
     * @return array{header: string|null, behindCloudflare: bool, behindAppPlatform: bool, viaAppPlatform: bool, connectingFromCloudflare: bool, cfConnectingIp: bool, trusted: bool, country: string|null}
     */
    public static function diagnose(Request $request): array
    {
        $connecting = trim((string) $request->headers->get('do-connecting-ip'));
        $via = filter_var($connecting, FILTER_VALIDATE_IP) !== false;
        $header = $request->headers->get('cf-ipcountry');
        $country = self::of($request);

        return [
            'header' => is_string($header) && $header !== '' ? mb_substr($header, 0, 8) : null,
            'behindCloudflare' => config('selfhost.behind_cloudflare') === true,
            'behindAppPlatform' => (bool) config('proxies.behind_app_platform'),
            'viaAppPlatform' => $via,
            'connectingFromCloudflare' => $via && IpUtils::checkIp($connecting, (array) config('proxies.cloudflare')),
            'cfConnectingIp' => $request->headers->has('cf-connecting-ip'),
            'trusted' => $country !== null,
            'country' => $country,
        ];
    }

    /**
     * On signing in: the account's country becomes this one, when it's known and different.
     */
    public static function remember(User $user, Request $request): void
    {
        $country = self::of($request);

        if ($country !== null && $user->country !== $country) {
            $user->forceFill(['country' => $country])->saveQuietly();
        }
    }
}
