<?php

declare(strict_types=1);

namespace App\Http\Resources\Sharing;

use App\Models\ShareVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * contract/schemas/video.json. Once ready, `url` downloads the MP4 as an attachment named after
 * the harmony's title. Needs harmony loaded.
 *
 * @property ShareVideo $resource
 */
final class ShareVideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $video = $this->resource;
        $expired = $video->status === ShareVideo::EXPIRED || ($video->expires_at !== null && $video->expires_at->isPast());
        $ready = $video->status === ShareVideo::READY && ! $expired && $video->file_key !== null;
        $name = Str::slug($video->harmony->title) ?: 'stackapella';

        return [
            'id' => $video->sqid(),
            'harmonyId' => $video->harmony->public_id,
            'status' => $expired ? ShareVideo::EXPIRED : $video->status,
            'progress' => (float) $video->progress,
            'failure' => $video->failure,
            'startSeconds' => $video->start_ms / 1000,
            'endSeconds' => $video->end_ms / 1000,
            'look' => $video->look,
            'words' => $video->words,
            'url' => $ready ? Storage::disk('private')->temporaryUrl((string) $video->file_key, now()->addMinutes(15), ['ResponseContentDisposition' => 'attachment; filename="'.$name.'.mp4"', 'ResponseContentType' => 'video/mp4']) : null,
            'bytes' => $ready ? $video->bytes : null,
            'expiresAt' => $video->expires_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $video->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
