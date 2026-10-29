<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An anonymous read (a GET or HEAD with no session or remember cookie, and no app token) starts no
 * session: it lives only for the request (the array driver) and the answer sets neither the
 * session's cookie nor XSRF-TOKEN. A client that keeps no cookies (a crawler, a script, a load
 * test) used to write a session row on every request (docs/performance.md).
 *
 * auth.config is the one read that still starts one: the app calls it first, and its answer brings
 * the session and the XSRF-TOKEN cookie every change needs (and the 419 recovery asks it again).
 */
final class SessionlessAnonymousReads
{
    /** The anonymous reads that start a session. */
    public const STARTS_SESSION = ['auth.config'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::anonymousRead($request)) {
            return $next($request);
        }

        $driver = config('session.driver');
        config(['session.driver' => 'array', 'session.stored_driver' => $driver]);

        try {
            $response = $next($request);
        } finally {
            config(['session.driver' => $driver, 'session.stored_driver' => null]);
        }

        foreach ($response->headers->getCookies() as $cookie) {
            if (in_array($cookie->getName(), [(string) config('session.cookie'), 'XSRF-TOKEN'], true)) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        return $response;
    }

    private static function anonymousRead(Request $request): bool
    {
        if (! $request->isMethodSafe() || $request->attributes->get(AuthenticateAppDevice::APP) === true || in_array($request->route()?->getName(), self::STARTS_SESSION, true)) {
            return false;
        }

        foreach (array_keys($request->cookies->all()) as $name) {
            if ($name === config('session.cookie') || str_starts_with((string) $name, 'remember_')) {
                return false;
            }
        }

        return true;
    }
}
