<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OwnChoir;

use App\Actions\OwnChoir\ChoirTogether;
use App\Http\Controllers\Controller;
use App\Http\Requests\OwnChoir\AcceptChoirInviteRequest;
use App\Http\Requests\OwnChoir\CreateChoirInviteRequest;
use App\Models\OwnChoirInvite;
use App\Models\OwnChoirMember;
use App\Models\OwnChoirRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * A choir with friends (docs/choir-together-plan.md; contract "A choir with friends"), behind own_choir_together.
 */
final class ChoirTogetherController extends Controller
{
    public function invite(CreateChoirInviteRequest $request, OwnChoirRequest $song, ChoirTogether $together, #[CurrentUser] User $user): JsonResponse
    {
        $parts = $request->validated('parts');
        ['invite' => $invite, 'url' => $url] = $together->invite($user, $song, is_array($parts) ? array_values(array_map('strval', $parts)) : null);

        return response()->json(['data' => ['id' => $invite->sqid(), 'url' => $url, 'parts' => array_values($invite->parts), 'expiresAt' => $invite->expires_at->utc()->toIso8601ZuluString()]], 201);
    }

    public function revoke(OwnChoirInvite $invite, ChoirTogether $together, #[CurrentUser] User $user): Response
    {
        $together->revoke($user, $invite);

        return response()->noContent();
    }

    public function show(Request $request, string $token, ChoirTogether $together): JsonResponse
    {
        $viewer = $request->user();
        $partKey = $request->query('partKey');

        return response()->json(['data' => $together->show($token, $viewer instanceof User ? $viewer : null, is_string($partKey) && $partKey !== '' ? $partKey : null, $request->boolean('useSavedVoice'))]);
    }

    public function accept(AcceptChoirInviteRequest $request, string $token, ChoirTogether $together, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{parts?: list<string>, showName: bool, wordsSha?: string|null, useSavedVoice?: bool|null, partKey?: string|null, offsetFrames?: int|null, sampleRate?: int|null, frames?: int|null, bytes?: int|null, sha256?: string|null} $data */
        $data = $request->validated();
        ['member' => $member, 'put' => $put] = $together->accept($user, $token, $data);

        return response()->json([
            'data' => ChoirTogether::member($member->load(['user', 'job'])),
            'upload' => $put === null ? null : [...$put, 'expiresAt' => Carbon::instance($put['expiresAt'])->utc()->toIso8601ZuluString()],
        ], 201);
    }

    public function complete(OwnChoirMember $member, ChoirTogether $together, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => ChoirTogether::member($together->complete($user, $member)->load(['user', 'job']))]);
    }

    public function leave(OwnChoirMember $member, ChoirTogether $together, #[CurrentUser] User $user): Response
    {
        $together->leave($user, $member);

        return response()->noContent();
    }
}
