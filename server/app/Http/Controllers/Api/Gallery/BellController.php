<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Gallery;

use App\Actions\Boards\Boards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\ReadBellRequest;
use App\Models\BellItem;
use App\Models\HarmonyLike;
use App\Models\User;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * notifications.list and .read (N1): the bell, in the app only. It answers while the gallery is
 * closed too (empty), so the bell can live in the app shell without asking a flag. Nothing from
 * someone either of them blocked.
 */
final class BellController extends Controller
{
    /** @var array<int, BellItem|null> each like's stand-in, worked out once a request */
    private array $standIns = [];

    /**
     * The request's own gallery (a scoped service): the route keeps this controller, so it's never
     * held from one request to the next, or its blocked list would go stale.
     */
    private function gallery(): Gallery
    {
        return app(Gallery::class);
    }

    public function index(#[CurrentUser] User $user): JsonResponse
    {
        // The route keeps this controller between requests: stand-ins are this request's own.
        $this->standIns = [];
        $page = $this->items($user)->with(['actor', 'harmony', 'idea.template', 'songRequest', 'partScore.template', 'partScore.templateVersion'])->orderByDesc('id')->cursorPaginate(30);
        $blocked = $this->gallery()->blockedIds($user);
        // A day's likes named after someone you blocked: the latest liker you haven't, the rest
        // counted (Hillel: "Name someone not blocked"); gone only if every liker is blocked.
        $items = array_values(array_filter(array_map(fn (BellItem $item): ?BellItem => $this->unblocked($item, $blocked), $page->items())));

        return response()->json([
            'data' => array_values(array_map(static fn (BellItem $item): array => [
                'id' => $item->sqid(),
                'kind' => $item->kind,
                // A friend who beat your score shows as on the friends' board (under 18: first name only),
                // and a like from someone under 18 as no one in particular.
                'actor' => GalleryViews::bellActor($item),
                'others' => $item->others,
                'harmony' => $item->harmony !== null && $item->harmony->deleted_at === null ? ['id' => $item->harmony->public_id, 'title' => $item->harmony->title] : null,
                'idea' => $item->idea !== null ? ['id' => $item->idea->public_id, 'title' => $item->idea->template->title] : null,
                'request' => $item->songRequest !== null ? ['id' => $item->songRequest->sqid(), 'title' => $item->songRequest->title] : null,
                'board' => $item->partScore !== null ? Boards::bellBoard($item->partScore) : null,
                'read' => $item->read_at !== null,
                'createdAt' => $item->created_at?->utc()->toIso8601ZuluString(),
            ], $items)),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode(), 'unread' => $this->unread($user)],
        ]);
    }

    public function read(ReadBellRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->standIns = [];
        $upTo = $request->validated('upTo');
        $id = is_string($upTo) ? Ids::decode($upTo) : null;

        BellItem::query()->where('user_id', $user->id)->whereNull('read_at')
            ->when(! $request->boolean('all') && $id !== null, fn ($query) => $query->where('id', '<=', $id))
            ->when(! $request->boolean('all') && $id === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['unread' => $this->unread($user)]]);
    }

    /**
     * @return Builder<BellItem>
     */
    private function items(User $user): Builder
    {
        $blocked = $this->gallery()->blockedIds($user);

        // A like may still be named after someone else who liked it that day (unblocked()).
        return BellItem::query()->where('user_id', $user->id)
            ->when($blocked !== [], fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('kind', 'like')->orWhereNull('actor_id')->orWhereNotIn('actor_id', $blocked)));
    }

    /**
     * Unread, as the list shows them: a like every liker of which is blocked doesn't count.
     */
    private function unread(User $user): int
    {
        $blocked = $this->gallery()->blockedIds($user);
        $unread = $this->items($user)->whereNull('read_at')->count();

        if ($blocked === []) {
            return $unread;
        }

        $hidden = BellItem::query()->where('user_id', $user->id)->whereNull('read_at')->where('kind', 'like')->whereIn('actor_id', $blocked)->get()
            ->filter(fn (BellItem $item): bool => $this->unblocked($item, $blocked) === null)->count();

        return max(0, $unread - $hidden);
    }

    /**
     * The item as the person may see it: a like whose latest liker they blocked is named after the
     * latest one that day they haven't (the rest counted), or null when they've blocked every liker.
     *
     * @param  list<int>  $blocked
     */
    private function unblocked(BellItem $item, array $blocked): ?BellItem
    {
        if ($item->kind !== 'like' || $item->actor_id === null || ! in_array($item->actor_id, $blocked, true)) {
            return $item;
        }

        if (array_key_exists($item->id, $this->standIns)) {
            return $this->standIns[$item->id];
        }

        $day = substr((string) $item->group_key, strrpos((string) $item->group_key, ':') + 1);
        $likers = HarmonyLike::query()->with('user')->where('harmony_id', $item->harmony_id)->where('user_id', '!=', $item->user_id)->whereNotIn('user_id', $blocked)
            ->whereBetween('created_at', [Carbon::parse($day, 'UTC')->startOfDay(), Carbon::parse($day, 'UTC')->endOfDay()])
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        $latest = $likers->first();

        if ($latest === null || $latest->user === null) {
            return $this->standIns[$item->id] = null;
        }

        return $this->standIns[$item->id] = $item->setRelation('actor', $latest->user)->forceFill(['actor_id' => $latest->user_id, 'others' => $likers->count() - 1]);
    }
}
