<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSecurityEventsRequest;
use App\Http\Resources\Admin\SecurityEventResource;
use App\Models\SecurityEvent;
use App\Support\Http\CursorList;
use App\Support\Ids;
use Illuminate\Http\JsonResponse;

/**
 * admin.securityEvents.list: what happened, newest first.
 */
final class SecurityEventsController extends Controller
{
    public function __invoke(ListSecurityEventsRequest $request): JsonResponse
    {
        $type = $request->validated('type');
        $user = $request->validated('user');

        $page = SecurityEvent::query()
            ->with('user')
            ->when(is_string($type) && $type !== '', fn ($query) => $query->where('type', $type))
            ->when(is_string($user) && $user !== '', fn ($query) => $query->where('user_id', Ids::decode((string) $user) ?? 0))
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CursorList::response($page, SecurityEventResource::class, $request);
    }
}
