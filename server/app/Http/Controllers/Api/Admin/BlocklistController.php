<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Trust\AddBlock;
use App\Actions\Trust\LiftBlock;
use App\Enums\BlockType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListBlocksRequest;
use App\Http\Requests\Admin\StoreBlockRequest;
use App\Http\Resources\Admin\BlockResource;
use App\Models\Block;
use App\Models\User;
use App\Support\Http\CursorList;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * admin.blocklist.list, .create and .delete.
 */
final class BlocklistController extends Controller
{
    public function index(ListBlocksRequest $request): JsonResponse
    {
        $type = BlockType::tryFrom((string) $request->validated('type'));

        $page = Block::query()
            ->with('creator')
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CursorList::response($page, BlockResource::class, $request);
    }

    public function store(StoreBlockRequest $request, AddBlock $add, #[CurrentUser] User $admin): JsonResponse
    {
        $expiresAt = $request->validated('expiresAt');

        $block = $add->handle(
            BlockType::from((string) $request->validated('type')),
            (string) $request->validated('value'),
            (string) $request->validated('reason'),
            is_string($expiresAt) ? Carbon::parse($expiresAt)->utc() : null,
            $admin,
            $request->ip(),
        );

        return (new BlockResource($block))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Block $block, LiftBlock $lift, #[CurrentUser] User $admin): Response
    {
        $lift->handle($block, $admin, $request->ip());

        return response()->noContent();
    }
}
