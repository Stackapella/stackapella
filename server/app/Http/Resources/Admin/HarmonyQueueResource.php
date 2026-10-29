<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Harmony;
use App\Models\Report;
use App\Support\Http\Hosts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * contract/schemas/admin.harmony.item.json: a harmony in the trust and safety queue, with its
 * reports. Needs user and reports.reporter loaded, and takedowns_count (the owner's harmonies
 * Hillel hid or removed, ever).
 *
 * @property Harmony $resource
 */
final class HarmonyQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $harmony = $this->resource;
        $reports = $harmony->reports->sortBy('id')->values();
        $listen = $harmony->removed_at === null && $harmony->audio_key !== null;

        return [
            'harmony' => [
                'id' => $harmony->public_id,
                'title' => $harmony->title,
                'owner' => ['id' => $harmony->user->sqid(), 'name' => $harmony->user->name],
                'ownerTakedowns' => (int) ($harmony->getAttribute('takedowns_count') ?? 0),
                'visibility' => $harmony->visibility,
                'status' => $harmony->status,
                'moderation' => $harmony->moderation(),
                'hiddenReason' => $harmony->hidden_reason ?? ($harmony->user->isSuspended() ? 'suspended' : null),
                'hiddenAt' => $harmony->hidden_at?->utc()->toIso8601ZuluString(),
                'titleHeld' => $harmony->title_held_at !== null,
                'pageUrl' => Hosts::public('h/'.$harmony->public_id),
                'audioUrl' => $listen ? Storage::disk('private')->temporaryUrl((string) $harmony->audio_key, now()->addMinutes(15)) : null,
                'cardUrl' => $listen && $harmony->card_key !== null ? Storage::disk('private')->temporaryUrl($harmony->card_key, now()->addMinutes(15)) : null,
                'durationSeconds' => $harmony->durationSeconds(),
                'signUps' => $harmony->sign_ups,
                'createdAt' => $harmony->created_at?->utc()->toIso8601ZuluString(),
            ],
            'reports' => $reports->map(static fn (Report $report): array => [
                'id' => $report->sqid(),
                'reason' => $report->reason,
                'note' => $report->note,
                'reporter' => $report->reporter !== null ? ['id' => $report->reporter->sqid(), 'name' => $report->reporter->name] : null,
                'status' => $report->status,
                'createdAt' => $report->created_at?->utc()->toIso8601ZuluString(),
            ])->all(),
            'firstReportedAt' => $reports->first()?->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
