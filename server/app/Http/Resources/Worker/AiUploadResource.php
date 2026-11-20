<?php

declare(strict_types=1);

namespace App\Http\Resources\Worker;

use App\Models\AiUpload;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An AI master on its way to becoming a guide, for the worker (contract/schemas/worker.ai.upload.json).
 *
 * @property AiUpload $resource
 */
final class AiUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $upload = $this->resource;

        return [
            'id' => $upload->sqid(),
            'versionId' => Ids::encode($upload->template_version_id),
            'part' => $upload->part_key,
            'voice' => Ids::encode($upload->ai_voice_id),
            'status' => $upload->status->value,
            'failure' => $upload->failure,
            'guideId' => $upload->template_guide_id !== null ? Ids::encode($upload->template_guide_id) : null,
            'createdAt' => $upload->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
