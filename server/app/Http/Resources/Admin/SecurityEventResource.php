<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One security event (contract/schemas/admin.security_event.json). The raw IP while it's kept
 * (30 days), and the start of its HMAC, which links events across accounts.
 *
 * @property SecurityEvent $resource
 */
final class SecurityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->sqid(),
            'type' => $this->resource->type->value,
            'user' => PersonResource::of($this->resource->user),
            'ip' => $this->resource->ip,
            'ipHash' => $this->resource->ip_hash !== null ? substr($this->resource->ip_hash, 0, 12) : null,
            'context' => $this->resource->context === null || $this->resource->context === [] ? null : (object) $this->resource->context,
            'createdAt' => $this->resource->created_at->utc()->toIso8601ZuluString(),
        ];
    }
}
