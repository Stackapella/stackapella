<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Cloudflare\Turnstile;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In shield mode (an attack switch), the forms that bots go for need a Turnstile token
 * (contract/api.md, "Accounts and sign-in"): 403 turnstile_required without one, and
 * turnstile_failed when Cloudflare doesn't accept it.
 */
final class Shield
{
    public function __construct(private readonly Turnstile $turnstile) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->turnstile->required()) {
            return $next($request);
        }

        $token = $request->input('turnstileToken');

        if (! is_string($token) || $token === '') {
            return ApiError::response(403, 'turnstile_required');
        }

        if (! $this->turnstile->passes($token, $request->ip())) {
            return ApiError::response(403, 'turnstile_failed');
        }

        return $next($request);
    }
}
