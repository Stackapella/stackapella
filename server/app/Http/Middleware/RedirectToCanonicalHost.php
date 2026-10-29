<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Other names (www, the launch domain before launch, an old domain) send people to the public
 * site, keeping the path and query: a 301 for a page, a 308 for anything else (a POST stays a
 * POST). With `ops.redirect_temporary` (before launch) they're 302 and 307, never cached, so a
 * browser forgets them when the launch domain gets its own site (`ops.redirect_hosts`).
 */
final class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $hosts = (array) config('ops.redirect_hosts');

        if ($hosts !== [] && in_array(strtolower($request->getHost()), array_map('strtolower', $hosts), true)) {
            $base = Hosts::publicUrl();

            $page = $request->isMethod('GET') || $request->isMethod('HEAD');

            if (config('ops.redirect_temporary') === true) {
                return redirect()->away($base.$request->getRequestUri(), $page ? 302 : 307)->header('Cache-Control', 'no-store');
            }

            return redirect()->away($base.$request->getRequestUri(), $page ? 301 : 308);
        }

        return $next($request);
    }
}
