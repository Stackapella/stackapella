<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed in until they sign out (Hillel, 2026-10-29): when a visit's session is restored from the
 * remember cookie, the cookie is set again for its full life, so a browser that caps a cookie's
 * life (Chrome keeps one at most 400 days) only signs out someone who stayed away that long.
 */
final class RenewRememberCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard || ! is_string($value = $request->cookies->get($guard->getRecallerName()))) {
            return $next($request);
        }

        // Resolving the person here is what restores them from the cookie, before anything asks.
        $guard->user();
        $response = $next($request);

        if ($guard->viaRemember() && $guard->check()) {
            Cookie::queue(Cookie::make($guard->getRecallerName(), $value, (int) config('auth.guards.web.remember')));
        }

        return $response;
    }
}
