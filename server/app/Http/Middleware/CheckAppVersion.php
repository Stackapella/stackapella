<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An installed app can be days old, so the API changes additively. When an old version must stop
 * (app.min_app_version), its calls get 426 with the code app_outdated, and the app says "Update the
 * app". A call without X-App-Version (a script, the admin pages) is served.
 */
final class CheckAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $minimum = config('app.min_app_version');
        $version = $request->headers->get('X-App-Version');

        if (is_string($minimum) && $minimum !== '' && is_string($version) && $version !== '' && version_compare($version, $minimum, '<')) {
            return ApiError::response(426, 'app_outdated');
        }

        return $next($request);
    }
}
