<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Notices\Notices;
use App\Actions\Support\SupportInbox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideAppealRequest;
use App\Http\Requests\Admin\ListInboxRequest;
use App\Http\Requests\Admin\ReplySupportRequest;
use App\Http\Requests\Admin\UpdateSupportRequest;
use App\Models\Notice;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.support.* (the inbox and its answers) and admin.appeals.* (asking for a review).
 */
final class SupportController extends Controller
{
    public function index(ListInboxRequest $request): JsonResponse
    {
        $status = $request->validated('status') ?? SupportMessage::OPEN;
        $page = SupportMessage::query()->with('user')->when($status !== 'all', fn ($query) => $query->where('status', $status))->orderByDesc('id')->cursorPaginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (SupportMessage $m): array => SupportInbox::view($m))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function show(string $message): JsonResponse
    {
        return response()->json(['data' => SupportInbox::view(SupportInbox::find($message), full: true)]);
    }

    public function reply(ReplySupportRequest $request, string $message, SupportInbox $inbox, #[CurrentUser] User $admin): JsonResponse
    {
        $inbox->reply($admin, $message, (string) $request->validated('body'), $request->boolean('close'));

        return response()->json(['data' => SupportInbox::view(SupportInbox::find($message), full: true)]);
    }

    public function update(UpdateSupportRequest $request, string $message, SupportInbox $inbox, #[CurrentUser] User $admin): JsonResponse
    {
        $inbox->setStatus($message, (string) $request->validated('status'), $admin, $request->ip());

        return response()->json(['data' => SupportInbox::view(SupportInbox::find($message), full: true)]);
    }

    public function appeals(ListInboxRequest $request): JsonResponse
    {
        $decided = ($request->validated('status') ?? 'waiting') === 'decided';
        $page = Notice::query()->with(['user', 'subject'])->whereNotNull('appealed_at')->when($decided, fn ($q) => $q->whereNotNull('decided_at'), fn ($q) => $q->whereNull('decided_at'))->orderBy('appealed_at')->cursorPaginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (Notice $n): array => Notices::view($n, admin: true))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function decide(DecideAppealRequest $request, string $notice, Notices $notices, #[CurrentUser] User $admin): JsonResponse
    {
        $decided = $notices->decide($admin, $notice, (string) $request->validated('decision'), (string) $request->validated('note'), $request->ip());

        return response()->json(['data' => Notices::view($decided->load(['user', 'subject']), admin: true)]);
    }
}
