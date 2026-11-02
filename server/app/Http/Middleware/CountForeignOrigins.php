<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CodeWatch\CodeWatch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Code watch (docs/code-watch-plan.md): an API call from another website is counted for its domain,
 * after the answer has gone. Only the domain and a daily count are kept: no IP, no person.
 */
final class CountForeignOrigins
{
    public function __construct(private readonly CodeWatch $watch) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');

        if (is_string($origin) && $origin !== '' && $origin !== 'null') {
            $this->watch->count($origin);
        }
    }
}
