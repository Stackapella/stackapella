<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SongVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One synced recording as the app plays it (contract/api.md, songs.videos.list): the video, its facts from YouTube's
 * last check, and our sync map.
 *
 * @property SongVideo $resource
 */
final class SongVideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $v = $this->resource;

        return [
            'id' => $v->sqid(),
            'videoId' => $v->video_id,
            'title' => $v->title,
            'channel' => $v->channel_title,
            'durationSeconds' => $v->duration_seconds,
            'templateVersion' => $v->template_version,
            'form' => $v->form,
            'anchors' => $v->anchors,
            'harmony' => $v->harmony->value,
            'otherWords' => $v->other_words ?? [],
        ];
    }
}
