<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\EarlyAccessLink;
use App\Support\Accounts\EarlyAccess;
use App\Support\Sharing\SharedPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * inviteLink.show: whether Hillel's shared invite link still lets people in (docs/decisions.md,
 * "invites"). Never marks a visit as from mudeo.
 */
final class InviteLinkController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        $status = EarlyAccess::status($code, EarlyAccessLink::INVITE);

        // Hillel's invite link, opened while it works (an anonymous count by surface: SharedPage).
        if ($status === 'open') {
            SharedPage::opened($request, 'app');
        }

        return response()->json(['data' => ['status' => $status]]);
    }
}
