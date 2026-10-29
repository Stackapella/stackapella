<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Flags;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only mode, for an incident: everything still plays, nothing new is saved. Applied to the
 * API's changing requests, except the admin's, so the switch can always be turned off again.
 */
final class ReadOnlyMode
{
    public function __construct(private readonly Flags $flags) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && ! $request->is('api/admin/*') && $this->flags->on('read_only')) {
            return ApiError::response(423, 'read_only');
        }

        return $next($request);
    }
}
