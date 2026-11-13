<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's throttle, except that the public pages' limiter counts in this instance's own store (cache.instance_store,
 * the file store in production) instead of the database: every page view wrote a row there, so a traffic spike was a
 * write storm on the main database. Right while there's one web instance (.do/app.yaml, instance_count); Cloudflare's
 * rate limiting is the outer guard. Every other limiter (sign-in, uploads) still counts in the shared store.
 */
final class Throttle extends ThrottleRequests
{
    /** The limiters that count per instance. */
    public const ON_INSTANCE = ['public-pages'];

    /**
     * @param  Request  $request
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if (! in_array($maxAttempts, self::ON_INSTANCE, true)) {
            // As given: Laravel reads a named limiter only when it's called with just the name (func_num_args()).
            return parent::handle(...func_get_args());
        }

        $named = $this->limiter->limiter((string) $maxAttempts);
        $local = new RateLimiter(Cache::store((string) config('cache.instance_store', 'file')));
        if ($named !== null) {
            $local->for((string) $maxAttempts, $named);
        }

        return (new ThrottleRequests($local))->handle(...func_get_args());
    }
}
