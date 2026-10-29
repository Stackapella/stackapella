<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One blocklist entry (contract/schemas/admin.block.json).
 *
 * @property Block $resource
 */
final class BlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->sqid(),
            'type' => $this->resource->type->value,
            'value' => $this->resource->value,
            'reason' => $this->resource->reason,
            'expiresAt' => $this->resource->expires_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $this->resource->created_at?->utc()->toIso8601ZuluString(),
            'createdBy' => PersonResource::of($this->resource->creator),
        ];
    }
}
