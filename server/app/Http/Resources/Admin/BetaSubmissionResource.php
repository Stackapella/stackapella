<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Actions\Courses\CourseClips;
use App\Http\Resources\GuideRecording;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Support\Ids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/**
 * A beta take for the review queue (contract/schemas/admin.beta.submission.json). Needs
 * BetaSubmissionResource::RELATIONS loaded.
 *
 * @property BetaSubmission $resource
 */
final class BetaSubmissionResource extends JsonResource
{
    public const RELATIONS = ['assignment.singer.user', 'assignment.template.currentVersion', 'assignment.clip', 'templateVersion', 'guide.submission.assignment.singer.user'];

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
        $singer = $submission->assignment->singer;
        $template = $submission->assignment->template;
        /** @var TemplateGuide|null $guide */
        $guide = $submission->guide;

        return [
            'id' => $submission->sqid(),
            'singer' => ['id' => $singer->sqid(), 'name' => $singer->name, 'credit' => $singer->credit()],
            'assignmentId' => Ids::encode($submission->beta_assignment_id),
            'kind' => $template === null ? 'clip' : 'part',
            'template' => $template === null ? null : [
                'id' => $template->slug,
                'version' => (int) $submission->templateVersion?->version,
                'currentVersion' => (int) $template->currentVersion?->version,
                'title' => $template->title,
                'key' => $submission->music_key,
            ],
            'partKey' => $submission->part_key,
            // A course clip's brief, for the review sheet.
            'clip' => $submission->assignment->clip !== null ? CourseClips::brief($submission->assignment->clip) : null,
            'status' => $submission->status->value,
            'publish' => $submission->publish_status,
            'failure' => $submission->failure,
            'sampleRate' => $submission->sample_rate,
            'frames' => $submission->frames,
            'latencyFrames' => $submission->latency_frames,
            'durationSeconds' => round($submission->durationSeconds(), 3),
            'loudnessDbfs' => $submission->loudness_dbfs !== null ? (float) $submission->loudness_dbfs : null,
            'device' => $submission->device,
            'mic' => $submission->mic,
            'scores' => $submission->scores === null || $submission->scores === [] ? null : (object) $submission->scores,
            'singerNote' => $submission->singer_note,
            'adjust' => ['offsetMs' => $submission->offset_ms, 'trimStartMs' => $submission->trim_start_ms, 'trimEndMs' => $submission->trim_end_ms, 'gainDb' => (float) $submission->gain_db],
            'adminNote' => $submission->admin_note,
            'adminNoteBeat' => $submission->admin_note_beat !== null ? (float) $submission->admin_note_beat : null,
            'guide' => $guide !== null ? GuideRecording::of($guide) : null,
            // When the singer was paid is Hillel's alone, never the editor's (docs/decisions.md).
            'paidAt' => Auth::user()?->is_admin === true ? $submission->paid_at?->utc()->toIso8601ZuluString() : null,
            'reviewedAt' => $submission->reviewed_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $submission->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
