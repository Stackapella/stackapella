<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\SongVideoResource as PublicResource;
use App\Models\SongVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One synced recording in the sync tool (contract/api.md, admin.videos.list): what singers get, plus its state and who
 * synced and approved it. Needs `template`, `syncer` and `approver` loaded.
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
            ...(new PublicResource($v))->toArray($request),
            'song' => $v->template->slug,
            'status' => $v->status->value,
            'hiddenReason' => $v->hidden_reason?->value,
            'checkedAt' => $v->checked_at->utc()->toIso8601ZuluString(),
            'syncedBy' => $v->syncer === null ? null : ['name' => (string) $v->syncer->name],
            'approvedBy' => $v->approver === null ? null : ['name' => (string) $v->approver->name],
            'approvedAt' => $v->approved_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
