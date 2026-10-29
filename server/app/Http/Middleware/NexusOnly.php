<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Our marketing site's pages (the landing, features, pricing, about...) answer only on
 * stackapella.com itself (config('app.is_nexus')). Elsewhere, an install of the app under the AAL,
 * they're not there: 404.
 */
final class NexusOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.is_nexus') !== true) {
            abort(404);
        }

        return $next($request);
    }
}
