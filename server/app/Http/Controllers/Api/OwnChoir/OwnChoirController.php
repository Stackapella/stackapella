<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OwnChoir;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\OwnChoir\OwnChoirConsentRequest;
use App\Http\Requests\OwnChoir\StartOwnChoirRequest;
use App\Models\OwnChoirRequest;
use App\Models\User;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * ownChoir.*: Hear yourself as a choir (track B phase 3), for the signed-in person, behind own_choir.
 */
final class OwnChoirController extends Controller
{
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        // Off, it's there only for someone with something of theirs to see and delete; for anyone else, nothing (never a
        // 404: Settings asks for everyone signed in).
        if (! OwnChoir::on($user) && ! OwnChoir::hasData($user)) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => OwnChoir::state($user)]);
    }

    public function consent(OwnChoirConsentRequest $request, OwnChoirRequests $choir, #[CurrentUser] User $user): JsonResponse
    {
        $choir->consent($user, (int) $request->validated('version'), $request->ip());

        return response()->json(['data' => OwnChoir::state($user)]);
    }

    public function store(StartOwnChoirRequest $request, OwnChoirRequests $choir, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{templateId: string, partKey?: string|null, offsetFrames?: int|null, sampleRate?: int|null, frames?: int|null, bytes?: int|null, sha256?: string|null, turnstileToken?: string|null} $clip */
        $clip = $request->validated();
        ['request' => $song, 'put' => $put] = $choir->start($user, $clip, $request->ip());

        return response()->json([
            'data' => OwnChoir::request($song->load(['templateVersion.template', 'parts']), null, $user),
            'upload' => $put === null ? null : [...$put, 'expiresAt' => Carbon::instance($put['expiresAt'])->utc()->toIso8601ZuluString()],
        ], 201);
    }

    public function complete(OwnChoirRequest $song, OwnChoirRequests $choir, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => OwnChoir::request($choir->complete($user, $song)->load(['templateVersion.template', 'parts']), null, $user)]);
    }

    public function seen(OwnChoirRequest $song, OwnChoirRequests $choir, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => OwnChoir::request($choir->seen($user, $song)->load(['templateVersion.template', 'parts']), null, $user)]);
    }

    public function download(OwnChoirRequest $song, OwnChoirRequests $choir, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => ['parts' => $choir->download($user, $song)]]);
    }

    public function destroy(OwnChoirRequest $song, OwnChoirRequests $choir, #[CurrentUser] User $user): Response
    {
        $choir->delete($user, $song);

        return response()->noContent();
    }

    public function deleteVoice(Request $request, OwnChoirRequests $choir, #[CurrentUser] User $user): Response
    {
        $choir->deleteVoice($user, $request->ip());

        return response()->noContent();
    }

    public function forget(Request $request, OwnChoirRequests $choir, #[CurrentUser] User $user): Response
    {
        $choir->forget($user, $request->ip());

        return response()->noContent();
    }
}
