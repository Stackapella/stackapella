<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Refusal;
use App\Support\Accounts\AppTokens;
use App\Support\Http\ApiError;
use App\Support\Trust\IpHash;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The native apps' bearer token (contract/api.md, "The apps: device tokens"): a valid one signs
 * the request in as its device's person, with no session and no CSRF check, and the answer sets
 * no cookie. An unknown or signed-out one is 401 unauthenticated; one past its hour, 401
 * token_expired (refresh, then repeat). Without a bearer token, nothing changes, except on the
 * apps' own sign-in routes, which are app requests too.
 *
 * An app request carries no cookies (any that came are dropped, so there's nothing ambient to
 * forge a request with) and gets none back, so the CSRF check has nothing to protect. Its session
 * lives only for the request (the array driver), so none is saved. Bearer tokens that fail are
 * counted per network, as guessing would be.
 */
final class AuthenticateAppDevice
{
    public const DEVICE = 'app.device';

    public const APP = 'app.request';

    /** On an open route, why the bearer token that came was refused. */
    public const REFUSED = 'app.refused';

    /**
     * The apps' routes before they have a device's token (or with one past its hour, on refresh):
     * a bearer token that fails is ignored here rather than refused.
     */
    public const OPEN = ['auth.app.signin', 'auth.app.google', 'auth.app.apple', 'auth.app.verify', 'auth.app.refresh', 'appPass.claim'];

    /** Unknown bearer tokens one network may send in a minute. */
    public const FAILURES_PER_MINUTE = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $open = in_array($request->route()?->getName(), self::OPEN, true);
        $token = $request->bearerToken();

        if (! $open && ($token === null || $token === '')) {
            return $next($request);
        }

        $request->attributes->set(self::APP, true);
        $request->cookies->replace([]);

        if ($token !== null && $token !== '') {
            try {
                $device = AppTokens::device($token);
                $request->attributes->set(self::DEVICE, $device);
                Auth::guard('web')->setUser($device->user);
            } catch (Refusal $refusal) {
                // A valid token always passes: only failing ones are counted and slowed, so one
                // phone retrying a signed-out token never locks out the rest of its network.
                $failures = 'app-bearer-failed:'.IpHash::of((string) $request->ip());

                if (RateLimiter::tooManyAttempts($failures, self::FAILURES_PER_MINUTE)) {
                    $wait = max(1, RateLimiter::availableIn($failures));

                    return ApiError::response(429, 'rate_limited', ['retryAfter' => $wait], ['Retry-After' => (string) $wait]);
                }

                if ($refusal->refusalCode === 'unauthenticated') {
                    RateLimiter::hit($failures, 60);
                }

                if (! $open) {
                    return ApiError::response($refusal->getStatusCode(), $refusal->refusalCode);
                }

                // An open route goes on without it, knowing why (linking past the hour says
                // token_expired, not signed out).
                $request->attributes->set(self::REFUSED, $refusal->refusalCode);
            }
        }

        $driver = config('session.driver');
        config(['session.driver' => 'array', 'session.stored_driver' => $driver]);

        try {
            $response = $next($request);
        } finally {
            config(['session.driver' => $driver, 'session.stored_driver' => null]);
        }

        // The app keeps no cookies: none are sent (the session's, XSRF-TOKEN, the beta pass).
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        return $response;
    }
}
