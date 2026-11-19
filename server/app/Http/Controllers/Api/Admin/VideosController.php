<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Videos\ApproveSongVideo;
use App\Actions\Videos\DeleteSongVideo;
use App\Actions\Videos\SaveSongVideo;
use App\Enums\SongVideoHarmony;
use App\Http\Controllers\Controller;
use App\Http\Requests\Videos\SaveSongVideoRequest;
use App\Http\Resources\Admin\SongVideoResource;
use App\Models\SongVideo;
use App\Models\User;
use App\Services\YouTube\YouTubeClient;
use App\Services\YouTube\YouTubeVideo;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The YouTube sync tool's server side (docs/youtube-sync-plan.md): a song's synced recordings in every state, a search
 * of YouTube (100 a day, so only here), saving a map, approving one, and taking one away. The admin's.
 */
final class VideosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['song' => ['nullable', 'string', 'max:120']]);
        $rows = SongVideo::query()->with('template', 'syncer', 'approver')
            ->when($request->query('song'), fn ($q, $slug) => $q->whereHas('template', fn ($t) => $t->where('slug', $slug)))
            ->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => SongVideoResource::collection($rows)->resolve($request)]);
    }

    public function search(Request $request, YouTubeClient $youtube): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:120']]);

        return response()->json(['data' => array_map(static fn (YouTubeVideo $v): array => $v->toArray(), $youtube->search((string) $request->query('q')))]);
    }

    public function store(SaveSongVideoRequest $request, SaveSongVideo $save, #[CurrentUser] User $user): JsonResponse
    {
        $row = $save->handle(
            $user,
            (string) $request->validated('song'),
            (int) $request->validated('templateVersion'),
            (string) $request->validated('videoId'),
            $request->form(),
            $request->pairs('anchors'),
            SongVideoHarmony::from((string) $request->validated('harmony')),
            $request->validated('otherWords') === null ? null : $request->pairs('otherWords'),
        );

        return response()->json(['data' => (new SongVideoResource($row))->resolve($request)], $row->wasRecentlyCreated ? 201 : 200);
    }

    public function approve(Request $request, string $video, ApproveSongVideo $approve, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => (new SongVideoResource($approve->handle($user, $video)))->resolve($request)]);
    }

    public function destroy(string $video, DeleteSongVideo $delete): Response
    {
        $delete->handle($video);

        return response()->noContent();
    }
}
