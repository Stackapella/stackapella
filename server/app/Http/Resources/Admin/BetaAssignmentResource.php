<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\BetaAssignment;
use App\Support\Beta\PartStatus;

/**
 * An assigned part, for Hillel (contract/schemas/admin.beta.assignment.json). Needs template,
 * latestSubmission and submissions.guide loaded.
 */
final class BetaAssignmentResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fields(BetaAssignment $assignment): array
    {
        return [
            'id' => $assignment->sqid(),
            'kind' => $assignment->course_clip_id !== null ? 'clip' : 'part',
            'templateId' => $assignment->template?->slug,
            'templateTitle' => $assignment->template?->title,
            'partKey' => $assignment->part_key,
            'clipId' => $assignment->clip?->slot,
            'minutes' => $assignment->minutes,
            'status' => PartStatus::of($assignment->latestSubmission, $assignment->isTheVoice()),
        ];
    }
}
