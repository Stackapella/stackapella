<?php

declare(strict_types=1);

namespace App\Http\Resources\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One take, as its singer sees it (contract/schemas/beta.submission.json). Needs templateVersion.
 *
 * @property BetaSubmission $resource
 */
final class BetaSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(BetaSubmission $submission): array
    {
        return [
            'id' => $submission->sqid(),
            'assignmentId' => Ids::encode($submission->beta_assignment_id),
            'status' => $submission->status->value,
            'failure' => $submission->failure,
            // Null for a course clip.
            'templateVersion' => $submission->templateVersion?->version,
            'durationSeconds' => round($submission->durationSeconds(), 3),
            'adminNote' => $submission->admin_note,
            'adminNoteBeat' => $submission->admin_note_beat !== null ? (float) $submission->admin_note_beat : null,
            // When Hillel wrote the note: it's written when he decides the take.
            'adminNoteAt' => $submission->admin_note !== null ? $submission->reviewed_at?->utc()->toIso8601ZuluString() : null,
            // Their own take, to hear again (a retake card): once it's processed, for 15 minutes.
            'url' => self::heard($submission) ? Storage::disk('private')->temporaryUrl($submission->master_key, now()->addMinutes(15)) : null,
            'createdAt' => $submission->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private static function heard(BetaSubmission $submission): bool
    {
        return $submission->master_key !== '' && ! in_array($submission->status, [SubmissionStatus::Uploading, SubmissionStatus::Processing, SubmissionStatus::Failed], true);
    }
}
