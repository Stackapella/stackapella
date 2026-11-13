<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Enums\CorrectionState;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Correction;
use App\Support\Trust\IpHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/internal/corrections: the readers' open corrections on the song and course pages, for app:pull-corrections
 * on the courses lead's machine (the daily triage, docs/corrections-plan.md), who has no production access. Read-only,
 * and never anything about the reader (the table keeps nothing; the answer names its fields one by one). Not part of the
 * apps' API, and outside the api group: no session, cookie or CSRF.
 *
 * Its own bearer token (services.corrections_export.token), from the Authorization header only, compared in constant
 * time; off (a 404) without one of at least MIN_TOKEN_LENGTH characters, so it can be revoked alone. Throttled per IP
 * (limits corrections-export), never cached, each pull and the first refused token an hour in the audit log.
 */
final class CorrectionsExportController extends Controller
{
    /** Anything shorter turns the endpoint off rather than guarding it with a guessable secret. */
    public const MIN_TOKEN_LENGTH = 32;

    /** The pages the courses lead triages: a song's, its parts', a course's and its lessons'. */
    public const PAGE_TYPES = ['song', 'part', 'course', 'lesson'];

    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.corrections_export.token');
        $headers = ['Cache-Control' => 'no-store, private'];

        if (strlen($expected) < self::MIN_TOKEN_LENGTH) {
            return response()->json(['error' => 'not_found'], 404, $headers);
        }

        $given = $request->bearerToken();
        $ip = (string) $request->ip();

        if (! is_string($given) || $given === '' || ! hash_equals($expected, $given)) {
            $code = $given === null || $given === '' ? 'no_bearer' : 'mismatch';
            if (Cache::add('corrections-export:refused:'.$code.':'.IpHash::of($ip), true, 3600)) {
                AuditLog::record('corrections.export_refused', null, null, after: ['code' => $code, 'ipHash' => IpHash::of($ip)]);
            }

            return response()->json(['error' => 'unauthorized'], 401, $headers);
        }

        $open = Correction::query()
            ->whereIn('state', [CorrectionState::New->value, CorrectionState::Accepted->value])
            ->whereIn('page_type', self::PAGE_TYPES)
            ->oldest('id')
            ->get();
        AuditLog::record('corrections.exported', null, null, after: ['count' => $open->count(), 'ipHash' => IpHash::of($ip)]);

        return response()->json([
            'meta' => ['generatedAt' => now()->utc()->toIso8601ZuluString(), 'count' => $open->count()],
            'corrections' => $open->map(static fn (Correction $c): array => [
                'id' => $c->sqid(),
                'createdAt' => $c->created_at?->utc()->toIso8601ZuluString(),
                'state' => $c->state->value,
                'kind' => $c->kind->value,
                'pageType' => $c->page_type,
                'pagePath' => $c->page_path,
                'pageTitle' => $c->page_title,
                'lang' => $c->lang,
                'text' => $c->text,
                'source' => $c->source,
            ])->values()->all(),
        ], 200, $headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
