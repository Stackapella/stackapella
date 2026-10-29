<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Beta\OpenInviteLink;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Resources\Beta\BetaMeResource;
use App\Models\User;
use App\Support\Beta\VoiceCount;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * beta.invite.show, beta.invite.accept and beta.me.show.
 */
final class InvitesController extends Controller
{
    public function show(string $token, OpenInviteLink $open): JsonResponse
    {
        return response()->json(['data' => $open->handle($token)]);
    }

    public function accept(string $token, AcceptInvite $accept, #[CurrentUser] User $user): BetaMeResource
    {
        $singer = $accept->handle($user, $token);

        return new BetaMeResource($singer, VoiceCount::of($singer));
    }

    public function me(Request $request): BetaMeResource
    {
        $singer = EnsureBetaSinger::singer($request);

        return new BetaMeResource($singer, VoiceCount::of($singer));
    }
}
