<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\SubmissionStatus;
use App\Exceptions\Refusal;
use App\Jobs\PublishGuide;
use App\Models\AuditLog;
use App\Models\BetaSubmission;
use App\Models\User;

/**
 * Hillel's decisions on a beta take (docs/beta-singers-plan.md, "The review queue"): open it,
 * nudge and trim it, then accept it as the guide, ask for another take, or not use it. Each
 * decision is audit-logged; only a take waiting for review can be decided.
 */
final class ReviewSubmission
{
    public function open(BetaSubmission $submission): BetaSubmission
    {
        if ($submission->status === SubmissionStatus::Submitted) {
            $submission->forceFill(['status' => SubmissionStatus::InReview])->save();
        }

        return $submission;
    }

    /**
     * @param  array{offsetMs?: int, trimStartMs?: int, trimEndMs?: int, gainDb?: float|int}  $adjust
     */
    public function adjust(BetaSubmission $submission, array $adjust, User $actor, ?string $ip = null): BetaSubmission
    {
        // Waiting for review, or accepted (to publish again with other trims): nothing else.
        if (! in_array($submission->status, [SubmissionStatus::Submitted, SubmissionStatus::InReview, SubmissionStatus::Accepted], true)) {
            throw new Refusal(409, 'not_reviewable');
        }

        $before = ['offset_ms' => $submission->offset_ms, 'trim_start_ms' => $submission->trim_start_ms, 'trim_end_ms' => $submission->trim_end_ms, 'gain_db' => (float) $submission->gain_db];

        $submission->forceFill(array_filter([
            'offset_ms' => $adjust['offsetMs'] ?? null,
            'trim_start_ms' => $adjust['trimStartMs'] ?? null,
            'trim_end_ms' => $adjust['trimEndMs'] ?? null,
            'gain_db' => $adjust['gainDb'] ?? null,
        ], static fn (mixed $value): bool => $value !== null))->save();

        AuditLog::record('beta.adjusted', $submission, $actor, before: $before, after: ['offset_ms' => $submission->offset_ms, 'trim_start_ms' => $submission->trim_start_ms, 'trim_end_ms' => $submission->trim_end_ms, 'gain_db' => (float) $submission->gain_db], ip: $ip);

        return $submission;
    }

    public function accept(BetaSubmission $submission, bool $makeDefault, User $actor, ?string $ip = null): BetaSubmission
    {
        $this->decide($submission, SubmissionStatus::Accepted, null, null, $actor, $ip);

        // A part is paid once, at the rate agreed now: the first accepted take of the assignment.
        $assignment = $submission->assignment;
        $alreadyPayable = BetaSubmission::query()->where('beta_assignment_id', $assignment->id)->whereKeyNot($submission->id)->where('payable_cents', '>', 0)->exists();
        $submission->forceFill([
            'publish_status' => 'pending',
            'payable_cents' => $alreadyPayable ? 0 : (int) round(($assignment->minutes ?? 0) / 60 * ($assignment->singer->hourly_rate_cents ?? 0)),
        ])->save();

        PublishGuide::dispatch($submission->id, $makeDefault);

        return $submission;
    }

    /**
     * Publish an accepted take again, with its current trims: its guide is replaced.
     */
    public function publish(BetaSubmission $submission, User $actor, ?string $ip = null): BetaSubmission
    {
        if ($submission->status !== SubmissionStatus::Accepted) {
            throw new Refusal(409, 'not_publishable');
        }

        $submission->forceFill(['publish_status' => 'pending'])->save();
        AuditLog::record('beta.published_again', $submission, $actor, ip: $ip);
        PublishGuide::dispatch($submission->id, false);

        return $submission;
    }

    public function retake(BetaSubmission $submission, string $note, ?float $beat, User $actor, ?string $ip = null): BetaSubmission
    {
        return $this->decide($submission, SubmissionStatus::Retake, $note, $beat, $actor, $ip);
    }

    public function reject(BetaSubmission $submission, ?string $note, User $actor, ?string $ip = null): BetaSubmission
    {
        return $this->decide($submission, SubmissionStatus::Rejected, $note, null, $actor, $ip);
    }

    private function decide(BetaSubmission $submission, SubmissionStatus $status, ?string $note, ?float $beat, User $actor, ?string $ip): BetaSubmission
    {
        // Claimed in one statement, so two reviewers (or two clicks) can't both decide it.
        $claimed = BetaSubmission::query()
            ->whereKey($submission->id)
            ->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::InReview->value])
            ->update(['status' => $status->value, 'admin_note' => $note, 'admin_note_beat' => $beat, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);

        if ($claimed !== 1) {
            throw new Refusal(409, 'not_reviewable');
        }

        $submission->refresh();
        AuditLog::record('beta.'.$status->value, $submission, $actor, after: array_filter(['note' => $note, 'beat' => $beat]), ip: $ip);

        return $submission;
    }
}
