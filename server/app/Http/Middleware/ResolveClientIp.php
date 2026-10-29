<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Trust\ClientIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first middleware: makes $request->ip() the client's real address (App\Support\Trust\ClientIp),
 * so every limiter, security event and audit entry after it sees the person, not a proxy.
 *
 * Behind App Platform, a request to the app's own ondigitalocean.app address is sent to the real
 * domain (except /up, which the health check calls there).
 */
final class ResolveClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = ClientIp::resolve(
            $request->server->getString('REMOTE_ADDR'),
            $request->headers->get('do-connecting-ip'),
            $request->headers->get('cf-connecting-ip'),
        );

        $request->server->set('REMOTE_ADDR', $ip);

        if (config('proxies.behind_app_platform') && str_ends_with($request->getHost(), '.ondigitalocean.app') && ! $request->is('up')) {
            // Temporary: the app's own default address is never one a browser should remember.
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 302)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
