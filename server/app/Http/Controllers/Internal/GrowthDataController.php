<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Growth\GrowthExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Trust\IpHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/internal/growth: the growth data (GrowthExport, docs/growth-data.md), for
 * app:pull-growth on Hillel's dev machine. Not part of the API the apps use (not in contract/api.md),
 * and outside the api group: no session, cookie or CSRF.
 *
 * One shared bearer token (services.growth_data.token), read from the Authorization header only: a
 * query parameter would put it in every access log it passes. Off (a 404) without a token of at least
 * MIN_TOKEN_LENGTH characters. Throttled per client IP (limits growth-data), one build at a time,
 * never cached, every pull written to the audit log and the first refused token an hour too.
 */
final class GrowthDataController extends Controller
{
    /** Anything shorter turns the endpoint off rather than guarding it with a guessable secret. */
    public const MIN_TOKEN_LENGTH = 32;

    /** Longer than any build; released the moment one ends, so it only matters if a worker dies. */
    private const LOCK_SECONDS = 300;

    public function __invoke(Request $request, GrowthExport $export): JsonResponse
    {
        $expected = (string) config('services.growth_data.token');
        $headers = ['Cache-Control' => 'no-store, private'];

        if (strlen($expected) < self::MIN_TOKEN_LENGTH) {
            return response()->json(['error' => 'not_found'], 404, $headers);
        }

        $given = $request->bearerToken();
        $ip = (string) $request->ip();

        if (! is_string($given) || $given === '' || ! hash_equals($expected, $given)) {
            // A fixed code, never the value sent ("no_bearer" is also what a proxy stripping the
            // header looks like). One row per address and code an hour: the path is public.
            $code = $given === null || $given === '' ? 'no_bearer' : 'mismatch';

            if (Cache::add('growth-data:refused:'.$code.':'.IpHash::of($ip), true, 3600)) {
                AuditLog::record('growth.refused', null, null, after: ['code' => $code, 'ipHash' => IpHash::of($ip)]);
            }

            return response()->json(['error' => 'unauthorized'], 401, $headers);
        }

        $lock = Cache::lock('growth-data:build', self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json(['error' => 'busy'], 429, [...$headers, 'Retry-After' => '30']);
        }

        try {
            $range = (string) $request->query('range', '30');
            $range = array_key_exists($range, GrowthExport::RANGES) ? $range : '30';
            $started = hrtime(true);
            $data = $export->build($range);

            AuditLog::record('growth.pulled', null, null, after: [
                'range' => $range,
                'ipHash' => IpHash::of($ip),
                'ms' => (int) round((hrtime(true) - $started) / 1e6),
                'peakMb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);

            return response()->json($data, 200, $headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } finally {
            $lock->release();
        }
    }
}
