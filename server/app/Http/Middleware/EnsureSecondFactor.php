<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Admin\SecondFactor;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin route needs a passkey (or the TOTP backup) checked since this sign-in. Someone with
 * neither is sent to set one up first; there is no way to skip it. The API answers 403
 * second_factor_required, and the app sends the person to the server's page.
 */
final class EnsureSecondFactor
{
    public function __construct(private readonly SecondFactor $secondFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->secondFactor->required()) {
            return $next($request);
        }

        /** @var User $user */
        $user = $request->user();

        if ($this->secondFactor->passedThisSession($request->session(), $user)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiError::response(403, 'second_factor_required');
        }

        $request->session()->put('url.intended', $request->getRequestUri());

        return redirect()->route($this->secondFactor->isEnrolled($user) ? 'admin.second-factor.challenge' : 'admin.second-factor.setup');
    }
}
