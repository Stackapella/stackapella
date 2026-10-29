<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contract/schemas/part.json. Needs project and currentTake loaded (for their UUIDs).
 *
 * @property Part $resource
 */
final class PartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fields(Part $part): array
    {
        return [
            'id' => $part->uuid,
            'projectId' => $part->project->uuid,
            'order' => $part->order,
            'name' => $part->name,
            'color' => $part->color,
            'volume' => (float) $part->volume,
            'pan' => (float) $part->pan,
            'muted' => $part->muted,
            'solo' => $part->solo,
            'nudgeFrames' => $part->nudge_frames,
            'trimStartFrames' => $part->trim_start_frames,
            'trimEndFrames' => $part->trim_end_frames,
            'templatePartKey' => $part->template_part_key,
            'kind' => $part->kind ?? 'voice',
            'currentTakeId' => $part->currentTake?->uuid,
            'settings' => (object) $part->settings,
            'version' => $part->version,
            'createdAt' => ($part->device_created_at ?? $part->created_at)?->utc()->toIso8601ZuluString(),
            'updatedAt' => $part->updated_at?->utc()->toIso8601ZuluString(),
            // On a shared project, whose part it is (lists eager load the creator).
            'createdBy' => $part->created_by !== null && ($creator = $part->loadMissing('creator')->creator) !== null ? ['id' => $creator->sqid(), 'name' => $creator->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }
}
