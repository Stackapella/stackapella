<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\SubmissionStatus;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\CourseClip;
use App\Models\Template;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The parts a singer is asked to record: add one, take one off before they've sent a take, or ask
 * for one again when the song changed.
 */
final class AssignPart
{
    /**
     * A course clip to record (CourseClips), once per singer.
     */
    public function addClip(BetaSinger $singer, CourseClip $clip, ?int $minutes, User $actor, ?string $ip = null): BetaAssignment
    {
        if (BetaAssignment::query()->where('beta_singer_id', $singer->id)->where('course_clip_id', $clip->id)->exists()) {
            throw ValidationException::withMessages(['clipId' => __('validation.clip_assigned')]);
        }

        $assignment = new BetaAssignment(['minutes' => $minutes]);
        $assignment->forceFill(['course_clip_id' => $clip->id]);
        $singer->assignments()->save($assignment);
        AuditLog::record('beta.assigned', $assignment, $actor, after: ['clip' => $clip->slot], ip: $ip);

        return $assignment->setRelation('clip', $clip)->setRelation('template', null)->setRelation('latestSubmission', null)->setRelation('submissions', collect());
    }

    public function add(BetaSinger $singer, Template $template, string $partKey, ?int $minutes, User $actor, ?string $ip = null): BetaAssignment
    {
        $assignment = new BetaAssignment(['template_id' => $template->id, 'part_key' => $partKey, 'minutes' => $minutes]);
        $singer->assignments()->save($assignment);
        AuditLog::record('beta.assigned', $assignment, $actor, after: ['template' => $template->slug, 'part' => $partKey], ip: $ip);

        return $assignment->setRelation('template', $template)->setRelation('latestSubmission', null)->setRelation('submissions', collect());
    }

    /**
     * Ask for a song's part again (a new version changed it): My parts shows it as a retake with
     * Hillel's note until a take comes. A volunteer's claim is held again for its usual days.
     */
    public function askAgain(BetaAssignment $assignment, ?string $note, User $actor, ?string $ip = null): BetaAssignment
    {
        if ($assignment->template_id === null) {
            throw new Refusal(409, 'not_a_part');
        }

        $singer = $assignment->singer;

        if ($singer->revoked_at !== null || $singer->forgotten_at !== null || $singer->volunteer_blocked_at !== null) {
            throw new Refusal(409, 'singer_gone');
        }

        $note = trim((string) $note);
        $assignment->forceFill([
            'asked_again_at' => now(),
            'asked_again_note' => $note !== '' ? $note : __('admin.beta.asked_again'),
            'expires_at' => $assignment->claimed_at !== null ? now()->addDays((int) config('beta.claim_days')) : $assignment->expires_at,
        ])->save();
        AuditLog::record('beta.asked_again', $assignment, $actor, after: ['part' => $assignment->part_key, 'note' => $assignment->asked_again_note], ip: $ip);

        return $assignment;
    }

    public function remove(BetaAssignment $assignment, User $actor, ?string $ip = null): void
    {
        // A take still uploading (perhaps abandoned) doesn't count.
        if ($assignment->submissions()->where('status', '!=', SubmissionStatus::Uploading->value)->exists()) {
            throw new Refusal(409, 'has_submissions');
        }

        AuditLog::record('beta.unassigned', $assignment, $actor, before: ['part' => $assignment->part_key, 'template' => $assignment->template_id], ip: $ip);
        $assignment->submissions()->delete();
        $assignment->delete();
    }
}
