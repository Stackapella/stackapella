<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Beta\ListFeedbackRequest;
use App\Http\Resources\Admin\BetaFeedbackResource;
use App\Models\BetaFeedback;
use App\Support\Http\CursorList;
use Illuminate\Http\JsonResponse;

/**
 * admin.beta.feedback.*: the Tell Hillel inbox.
 */
final class FeedbackController extends Controller
{
    public function index(ListFeedbackRequest $request): JsonResponse
    {
        $page = BetaFeedback::query()
            ->with('singer')
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CursorList::response($page, BetaFeedbackResource::class, $request);
    }

    public function read(BetaFeedback $feedback): BetaFeedbackResource
    {
        $feedback->markRead();

        return new BetaFeedbackResource($feedback->load('singer'));
    }
}
