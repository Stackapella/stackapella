<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Gallery;

use App\Actions\Boards\Boards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\ReadBellRequest;
use App\Models\BellItem;
use App\Models\User;
use App\Support\Gallery\GalleryViews;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * notifications.list and .read (N1): the bell, in the app only. It answers while the gallery is
 * closed too (empty), so the bell can live in the app shell without asking a flag.
 */
final class BellController extends Controller
{
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $page = BellItem::query()->with(['actor', 'harmony', 'idea.template', 'songRequest', 'partScore.template', 'partScore.templateVersion'])->where('user_id', $user->id)->orderByDesc('id')->cursorPaginate(30);

        return response()->json([
            'data' => array_values(array_map(static fn (BellItem $item): array => [
                'id' => $item->sqid(),
                'kind' => $item->kind,
                // A friend who beat your score shows as on the friends' board (under 18: first name only).
                'actor' => $item->actor !== null ? ['handle' => GalleryViews::person($item->actor)['handle'], 'name' => $item->kind === 'board_beaten' ? Boards::friendName($item->actor) : GalleryViews::person($item->actor)['name']] : null,
                'others' => $item->others,
                'harmony' => $item->harmony !== null && $item->harmony->deleted_at === null ? ['id' => $item->harmony->public_id, 'title' => $item->harmony->title] : null,
                'idea' => $item->idea !== null ? ['id' => $item->idea->public_id, 'title' => $item->idea->template->title] : null,
                'request' => $item->songRequest !== null ? ['id' => $item->songRequest->sqid(), 'title' => $item->songRequest->title] : null,
                'board' => $item->partScore !== null ? Boards::bellBoard($item->partScore) : null,
                'read' => $item->read_at !== null,
                'createdAt' => $item->created_at?->utc()->toIso8601ZuluString(),
            ], $page->items())),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode(), 'unread' => BellItem::query()->where('user_id', $user->id)->whereNull('read_at')->count()],
        ]);
    }

    public function read(ReadBellRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $upTo = $request->validated('upTo');
        $id = is_string($upTo) ? Ids::decode($upTo) : null;

        BellItem::query()->where('user_id', $user->id)->whereNull('read_at')
            ->when(! $request->boolean('all') && $id !== null, fn ($query) => $query->where('id', '<=', $id))
            ->when(! $request->boolean('all') && $id === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['unread' => BellItem::query()->where('user_id', $user->id)->whereNull('read_at')->count()]]);
    }
}
