<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\Hosts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Signed in?" for the public site (docs/tools-plan.md, "Signed in or not"): the public pages have no
 * session (decisions: sessions are scoped to the app's host) and stay cacheable, so a tool's page
 * can't ask the server. Instead the app's host keeps one cookie, sa_signed_in=1, on the domain both
 * hosts share (hosts.pass_domain), which the site's script reads to show "Open the app" in place of
 * the try-the-app line. Its value is only "1": no ID, nothing that tells one person from another;
 * the server looks at it only to know whether to set or clear it. Set on a signed-in visit, cleared
 * on the first visit whose session has no one (a sign-out, here or everywhere, or an ended
 * session), so it heals itself.
 */
final class SignedInHint
{
    public const COOKIE = 'sa_signed_in';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The native apps (a bearer token) keep no cookies; the public host has no session to read.
        if ($request->bearerToken() !== null || ! Hosts::onAppHost($request)) {
            return $response;
        }

        $has = $request->cookies->get(self::COOKIE) === '1';
        $signedIn = Auth::guard('web')->check();

        if ($signedIn && ! $has) {
            $response->headers->setCookie($this->cookie($request, '1', (int) config('auth.guards.web.remember')));
        } elseif (! $signedIn && $has && $request->hasSession() && $request->attributes->get(SessionlessAnonymousReads::SESSIONLESS) !== true) {
            // Only once a real session was read and holds no one: an anonymous read with no session
            // cookie (SessionlessAnonymousReads) says nothing either way.
            $response->headers->setCookie($this->cookie($request, '', -2628000));
        }

        return $response;
    }

    private function cookie(Request $request, string $value, int $minutes): Cookie
    {
        $domain = config('hosts.pass_domain');

        return Cookie::create(
            self::COOKIE,
            $value,
            time() + $minutes * 60,
            '/',
            is_string($domain) && $domain !== '' ? $domain : null,
            $request->isSecure() || app()->isProduction(),
            false,
            false,
            Cookie::SAMESITE_LAX,
        );
    }
}
