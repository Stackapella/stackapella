<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Correction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One correction in Admin > Inbox > Corrections (contract/api.md, admin.corrections.list). Needs `decider` loaded.
 *
 * @property Correction $resource
 */
final class CorrectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'id' => $c->sqid(),
            'kind' => $c->kind->value,
            'state' => $c->state->value,
            'text' => $c->text,
            'source' => $c->source,
            'page' => ['type' => $c->page_type, 'title' => $c->page_title, 'path' => $c->page_path],
            'lang' => $c->lang,
            'createdAt' => $c->created_at?->utc()->toIso8601ZuluString(),
            'decision' => $c->decided_at === null ? null : [
                'state' => $c->state->value,
                'note' => $c->note,
                'by' => ['name' => (string) ($c->decider->name ?? '')],
                'at' => $c->decided_at->utc()->toIso8601ZuluString(),
            ],
        ];
    }
}
