<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Support\Trust\IpHash;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AI voices worker API's one bearer token (services.ai_worker.token, from env only; contract/api.md, "The AI voices
 * worker"), read from the Authorization header alone, never a query parameter. Without a token of at least
 * MIN_TOKEN_LENGTH characters the API is off: 404. A missing or wrong token is 401, its first refusal from a network
 * each hour written to the audit log (a fixed code, never the value sent).
 */
final class AuthenticateAiWorker
{
    /** Anything shorter turns the API off rather than guarding it with a guessable secret. */
    public const MIN_TOKEN_LENGTH = 32;

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ai_worker.token');

        if (strlen($expected) < self::MIN_TOKEN_LENGTH) {
            throw new Refusal(404, 'not_found');
        }

        $given = $request->bearerToken();

        if (! is_string($given) || $given === '' || ! hash_equals($expected, $given)) {
            $code = $given === null || $given === '' ? 'no_bearer' : 'mismatch';
            $ip = IpHash::of((string) $request->ip());

            if (Cache::add('ai-worker:refused:'.$code.':'.$ip, true, 3600)) {
                AuditLog::record('ai.worker_refused', null, null, after: ['code' => $code, 'ipHash' => $ip]);
            }

            throw new Refusal(401, 'unauthenticated');
        }

        return $next($request);
    }
}
