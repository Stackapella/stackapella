<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Support;

use App\Actions\Notices\Notices;
use App\Actions\Support\SupportInbox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notices\AppealRequest;
use App\Http\Requests\Support\ContactRequest;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * support.create ("Contact us", "Send diagnostics") and notices.* (statements of reasons, and
 * asking for a review).
 */
final class SupportController extends Controller
{
    public function store(ContactRequest $request, SupportInbox $inbox, #[CurrentUser] ?User $user): JsonResponse
    {
        // A bot filled the honeypot: answered as any message is, and nothing kept.
        if (filled($request->input('website'))) {
            return response()->json(['data' => ['id' => 'received', 'receivedAt' => now()->utc()->toIso8601ZuluString()]], 201);
        }

        /** @var array{email?: string|null, category: string, message: string, context?: array<string, mixed>|null} $data */
        $data = $request->safe()->except('website');
        $message = $inbox->receive($user, $data, (string) $request->ip());

        return response()->json(['data' => ['id' => $message->sqid(), 'receivedAt' => $message->created_at?->utc()->toIso8601ZuluString()]], 201);
    }

    public function notices(#[CurrentUser] User $user): JsonResponse
    {
        $notices = Notice::query()->with(['user', 'subject'])->where('user_id', $user->id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $notices->map(fn (Notice $n): array => Notices::view($n))->values()->all()]);
    }

    public function notice(Request $request, string $notice, Notices $notices): JsonResponse
    {
        return response()->json(['data' => Notices::view($notices->find($notice, $request->user())->load(['user', 'subject']))]);
    }

    public function appeal(AppealRequest $request, string $notice, Notices $notices): JsonResponse
    {
        return response()->json(['data' => Notices::view($notices->appeal($notice, $request->user(), (string) $request->validated('text'))->load(['user', 'subject']))]);
    }
}
