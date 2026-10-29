<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAuditRequest;
use App\Http\Resources\Admin\AuditEntryResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Http\CursorList;
use App\Support\Ids;
use Illuminate\Http\JsonResponse;

/**
 * admin.audit.list: every admin, moderation and money action, newest first.
 */
final class AuditController extends Controller
{
    public function __invoke(ListAuditRequest $request): JsonResponse
    {
        $action = $request->validated('action');
        // One account's entries: about them, or done by them.
        $user = is_string($who = $request->validated('user')) ? Ids::decode($who) ?? 0 : null;

        $page = AuditLog::query()
            ->with('actor')
            ->when(is_string($action) && $action !== '', fn ($query) => $query->where('action', 'like', addcslashes((string) $action, '%_\\').'%'))
            ->when($user !== null, fn ($query) => $query->where(fn ($q) => $q->where(fn ($s) => $s->where('subject_type', (new User)->getMorphClass())->where('subject_id', $user))->orWhere('actor_id', $user)))
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CursorList::response($page, AuditEntryResource::class, $request);
    }
}
