<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Accounts\BetaPass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before launch, a signed-in person carries the beta pass (an account only exists by invitation
 * while sign-ups are paused), so the app opens for them at / too (App\Support\Accounts\BetaPass).
 */
final class GrantBetaPass
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('app.coming_soon') === true && $request->user() !== null && ! BetaPass::valid($request)) {
            $response->headers->setCookie(BetaPass::cookie($request));
        }

        return $response;
    }
}
