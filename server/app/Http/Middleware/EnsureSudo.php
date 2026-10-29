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
 * Sensitive actions (switches, limits, the blocklist, money, bans, deletes) need a second-factor
 * check within the last admin.sudo_window minutes. The API answers 403 sudo_required, and the app
 * sends the person to the check, which brings them back.
 */
final class EnsureSudo
{
    public function __construct(private readonly SecondFactor $secondFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->secondFactor->required() || $this->secondFactor->inSudoWindow($request->session(), $user)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiError::response(403, 'sudo_required');
        }

        $request->session()->put('url.intended', (string) parse_url(url()->previous(), PHP_URL_PATH));

        return redirect()->route('admin.second-factor.challenge', ['sudo' => 1]);
    }
}
