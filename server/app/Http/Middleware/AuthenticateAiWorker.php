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
 * The AI voices worker API's bearer tokens, from env only (contract/api.md, "The AI voices worker"): the catalogue's
 * (services.ai_worker.token, worker.ai.*) and the choir jobs' own (services.ai_worker.choir_token, worker.choir.*, as
 * `ai.worker:choir`), so neither opens the other. Read from the Authorization header alone, never a query parameter. Without a token of at least
 * MIN_TOKEN_LENGTH characters, or with both set to the same value, the API is off: 404. A missing or wrong token is 401, its first refusal from a network
 * each hour written to the audit log (a fixed code, never the value sent).
 */
final class AuthenticateAiWorker
{
    /** Anything shorter turns the API off rather than guarding it with a guessable secret. */
    public const MIN_TOKEN_LENGTH = 32;

    public function handle(Request $request, Closure $next, string $api = 'catalogue'): Response
    {
        $expected = (string) config($api === 'choir' ? 'services.ai_worker.choir_token' : 'services.ai_worker.token');

        if (strlen($expected) < self::MIN_TOKEN_LENGTH || self::shared()) {
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

    /**
     * Both tokens set to the same value: neither API opens (the catalogue's machine could claim a user's private clip),
     * and it's reported once an hour so the deploy is fixed.
     */
    private static function shared(): bool
    {
        $catalogue = (string) config('services.ai_worker.token');
        $choir = (string) config('services.ai_worker.choir_token');

        if ($catalogue === '' || ! hash_equals($catalogue, $choir)) {
            return false;
        }

        if (Cache::add('ai-worker:shared-token', true, 3600)) {
            report(new \RuntimeException('AI_WORKER_TOKEN and AI_CHOIR_TOKEN are the same: both worker APIs are off until they differ.'));
        }

        return true;
    }
}
