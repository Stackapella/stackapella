<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Gallery;

use App\Actions\Gallery\OpenParts;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\OpenPartsRequest;
use App\Http\Resources\Sharing\HarmonyResource;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * harmonies.open, harmonies.stems and harmonies.fills.list (N5): parts left open, what a filler
 * sings with, and the fills sung for a harmony.
 */
final class OpenPartsController extends Controller
{
    public function __construct(private readonly OpenParts $parts) {}

    public function open(OpenPartsRequest $request, string $harmony, #[CurrentUser] User $user): JsonResponse
    {
        /** @var list<string> $keys */
        $keys = array_values(array_map('strval', (array) $request->validated('parts')));
        $opened = $this->parts->open($user, $harmony, $keys, $request->boolean('guide', true), $request->ip());

        return (new HarmonyResource($opened->load(['project', 'part', 'user', 'templateVersion'])))->response();
    }

    public function stems(string $harmony, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->parts->stems($user, $harmony)]);
    }

    public function fills(Request $request, string $harmony, Gallery $gallery, GalleryViews $views): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:500']]);
        $viewer = $request->user() instanceof User ? $request->user() : null;
        $original = Harmony::query()->with('user')->where('public_id', $harmony)->whereNull('deleted_at')->first();

        if ($original === null || ! $this->parts->isOpen() || ! $gallery->isOpen() || ! $gallery->isPublic($original) || ($viewer !== null && $gallery->blockedBetween($viewer, $original->user_id))) {
            throw new Refusal(404, 'not_found');
        }

        // Never a fill by someone its original's owner has blocked (or who blocked them).
        $page = $gallery->listed($viewer)->with(GalleryViews::WITH)->where('parent_id', $original->id)
            ->when(($blocked = $gallery->blockedIds($original->user)) !== [], fn ($query) => $query->whereNotIn('user_id', $blocked))
            ->when($original->featured_fill_id !== null, fn ($query) => $query->whereKeyNot($original->featured_fill_id))
            ->orderByDesc('id')->cursorPaginate(24);
        $items = collect($page->items());

        // The featured one first, on the first page.
        if ($request->query('cursor') === null && $original->featured_fill_id !== null) {
            $featured = $gallery->listed($viewer)->with(GalleryViews::WITH)->whereKey($original->featured_fill_id)->first();
            $items = $featured !== null ? $items->prepend($featured) : $items;
        }

        return response()->json(['data' => $views->harmonies($items, $viewer), 'meta' => ['nextCursor' => $page->nextCursor()?->encode()]]);
    }
}
