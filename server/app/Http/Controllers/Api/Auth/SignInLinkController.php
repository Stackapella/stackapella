<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Users\SignInByLink;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendSignInLinkRequest;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\AccountResource;
use App\Jobs\SendSignInLink;
use App\Support\Accounts\Age;
use App\Support\Accounts\SignInSession;
use App\Support\Trust\Blocklist;
use App\Support\Trust\SecurityLog;
use Illuminate\Http\JsonResponse;

/**
 * auth.link.send and auth.link.open: sign in by a link sent by email (SignInByLink).
 */
final class SignInLinkController extends Controller
{
    public function send(SendSignInLinkRequest $request, Blocklist $blocklist, SecurityLog $securityLog): JsonResponse
    {
        if (($block = $blocklist->find(email: (string) $request->validated('email'), ip: $request->ip())) !== null) {
            $securityLog->record(SecurityEventType::Blocked, null, $request->ip(), ['where' => 'sign_in_link', 'block' => $block->id]);

            throw new Refusal(403, 'blocked');
        }

        $text = static fn (string $key): ?string => is_string($v = $request->validated($key)) && $v !== '' ? $v : null;
        SendSignInLink::dispatch((string) $request->validated('email'), $text('intended'), $text('invite'), $text('early'), $request->ip());

        return response()->json(['data' => ['status' => 'check_email']], 202);
    }

    public function open(TokenRequest $request, SignInByLink $links, SignInSession $session, AcceptInvite $acceptInvite): JsonResponse
    {
        $opened = $links->open((string) $request->validated('token'), $request->ip());
        $session->start($request, $opened['user'], 'link');
        // The beta invite the link carried, now that the address is proven.
        $acceptInvite->pending($opened['user']);
        $user = $opened['user']->refresh();

        return response()->json([
            'data' => (new AccountResource($user))->toArray($request),
            'created' => $opened['created'],
            'intended' => $opened['intended'],
            // A name, and when they were born (or "13 or over"), are asked before anything else.
            'needsProfile' => trim($user->name) === '' || ! Age::given($user),
        ]);
    }
}
