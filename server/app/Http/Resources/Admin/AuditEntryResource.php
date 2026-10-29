<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\AuditLog;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One audit log entry (contract/schemas/admin.audit_entry.json). The subject is named by its
 * morph alias and Sqid, without loading it (it may since have been deleted).
 *
 * @property AuditLog $resource
 */
final class AuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->sqid(),
            'action' => $log->action,
            'actor' => PersonResource::of($log->actor),
            'subject' => $log->subject_type !== null && $log->subject_id !== null ? ['type' => $log->subject_type, 'id' => Ids::encode((int) $log->subject_id)] : null,
            'before' => $log->before === null ? null : (object) $log->before,
            'after' => $log->after === null ? null : (object) $log->after,
            'reason' => $log->reason,
            'createdAt' => $log->created_at->utc()->toIso8601ZuluString(),
        ];
    }
}
