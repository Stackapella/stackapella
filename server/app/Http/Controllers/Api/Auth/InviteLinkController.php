<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\EarlyAccessLink;
use App\Support\Accounts\EarlyAccess;
use Illuminate\Http\JsonResponse;

/**
 * inviteLink.show: whether Hillel's shared invite link still lets people in (docs/decisions.md,
 * "invites"). Never marks a visit as from mudeo.
 */
final class InviteLinkController extends Controller
{
    public function __invoke(string $code): JsonResponse
    {
        return response()->json(['data' => ['status' => EarlyAccess::status($code, EarlyAccessLink::INVITE)]]);
    }
}
