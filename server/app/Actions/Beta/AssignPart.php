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
 * The parts a singer is asked to record: add one, or take one off before they've sent a take.
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
