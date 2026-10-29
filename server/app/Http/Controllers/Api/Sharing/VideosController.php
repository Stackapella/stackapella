<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sharing;

use App\Actions\Sharing\StartShareVideo;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sharing\StartVideoRequest;
use App\Http\Resources\Sharing\ShareVideoResource;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * videos.create and .show: Share as a video, for the harmony's owner.
 */
final class VideosController extends Controller
{
    public function store(StartVideoRequest $request, string $harmony, StartShareVideo $start, #[CurrentUser] User $user): JsonResponse
    {
        $found = Harmony::query()->with('user')->where('public_id', $harmony)->where('user_id', $user->id)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');

        /** @var array{startSeconds: float|int, endSeconds: float|int, look?: string|null, words?: bool|null} $data */
        $data = $request->validated();
        $started = $start->handle($found, $data, (string) $request->ip());

        return (new ShareVideoResource($started['video']->setRelation('harmony', $found)))->response()->setStatusCode($started['created'] ? 202 : 200);
    }

    public function show(string $video, #[CurrentUser] User $user): JsonResponse
    {
        $found = ShareVideo::query()->with('harmony')->whereSqid($video)->where('user_id', $user->id)->first() ?? throw new Refusal(404, 'not_found');

        return (new ShareVideoResource($found))->response();
    }
}
