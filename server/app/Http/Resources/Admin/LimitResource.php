<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One rule of a named limiter (contract/schemas/admin.limit.json), with who overrode it, when and
 * why (all null without an override).
 *
 * @property array{limiter: string, rule: string, max: int, per: string, by: string, default: int, override: int|null, change: Setting|null} $resource
 */
final class LimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'limiter' => $this->resource['limiter'],
            'rule' => $this->resource['rule'],
            'max' => $this->resource['max'],
            'per' => $this->resource['per'],
            'by' => $this->resource['by'],
            'default' => $this->resource['default'],
            'override' => $this->resource['override'],
            'reason' => $this->resource['change']?->reason,
            'changedAt' => $this->resource['change']?->updated_at?->utc()->toIso8601ZuluString(),
            'changedBy' => PersonResource::of($this->resource['change']?->updater),
        ];
    }
}
