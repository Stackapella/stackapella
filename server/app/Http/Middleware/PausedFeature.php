<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Flags;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `paused:uploads` and the like: while the switch that pauses this feature is on
 * (config/flags.php), new requests get 423 with the code `paused` (never a 503: the hosting's edge swallows its body), and the app keeps the work on
 * the device. Reading still works.
 */
final class PausedFeature
{
    public function __construct(private readonly Flags $flags) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->flags->pausing($feature) !== null) {
            return ApiError::response(423, 'paused');
        }

        return $next($request);
    }
}
