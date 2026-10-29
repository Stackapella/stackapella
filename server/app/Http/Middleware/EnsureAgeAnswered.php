<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploading, sharing, joining and buying need the "I'm 13 or over" answer (contract/api.md,
 * "Accounts and sign-in"): the app asks it first, and this refuses anyone who hasn't answered
 * (`403 age_unconfirmed`), so an API call can't skip it. A first sign-in with Google, Apple or
 * Event Schedule makes an account that hasn't answered yet.
 */
final class EnsureAgeAnswered
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->age_confirmed_at === null) {
            return ApiError::response(403, 'age_unconfirmed');
        }

        return $next($request);
    }
}
