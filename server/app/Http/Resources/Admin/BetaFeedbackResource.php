<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\BetaFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Tell Hillel note (contract/schemas/admin.beta.feedback_item.json). Needs singer loaded.
 *
 * @property BetaFeedback $resource
 */
final class BetaFeedbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $feedback = $this->resource;

        return [
            'id' => $feedback->sqid(),
            'singer' => ['id' => $feedback->singer->sqid(), 'name' => $feedback->singer->name],
            'templateId' => $feedback->template_slug,
            'partKey' => $feedback->part_key,
            'beat' => $feedback->beat !== null ? (float) $feedback->beat : null,
            'text' => $feedback->text,
            'readAt' => $feedback->read_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $feedback->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
