<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Resources\SongVideoResource;
use App\Models\SongVideo;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * songs.videos.list: a song's real recordings on YouTube that someone synced and someone else approved
 * (docs/youtube-sync-plan.md), for the Sing screen's "Real recordings". Empty while the youtube_sync switch is off for
 * this person.
 */
final class SongVideosController extends Controller
{
    public function __invoke(Request $request, string $template, Flags $flags, #[CurrentUser] ?User $user): JsonResponse
    {
        $song = Template::query()->listed()->where('slug', $template)->first() ?? throw new Refusal(404, 'not_found');
        $videos = $flags->onFor('youtube_sync', $user)
            ? SongVideo::query()->shown()->where('template_id', $song->id)->orderBy('id')->get()
            : collect();

        return response()->json(['data' => SongVideoResource::collection($videos)->resolve($request)]);
    }
}
