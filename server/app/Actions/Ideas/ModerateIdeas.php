<?php

declare(strict_types=1);

namespace App\Actions\Ideas;

use App\Actions\Notices\Notices;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Notice;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hillel's side of Ideas: approve one waiting for review, hide an idea or one voice (its writer or
 * singer gets a notice they can appeal), or show it again. Every action is audited.
 */
final class ModerateIdeas
{
    public function __construct(private readonly Notices $notices, private readonly Ideas $ideas) {}

    public function decide(Idea $idea, ?IdeaVoice $voice, User $admin, string $decision, string $note, ?string $ip): void
    {
        DB::transaction(function () use ($idea, $voice, $admin, $decision, $note, $ip): void {
            $locked = Idea::query()->with('user')->whereKey($idea->id)->lockForUpdate()->firstOrFail();

            if ($voice !== null) {
                $this->decideVoice($locked, $voice, $admin, $decision, $note, $ip);

                return;
            }

            match ($decision) {
                'approve' => $locked->held_at === null || $locked->hidden_at !== null ? throw new Refusal(409, 'not_held') : $locked->forceFill(['held_at' => null])->save(),
                'hide' => $locked->hidden_at === null ? $this->hide($locked, $admin, $note) : null,
                default => $locked->hidden_at === null ? throw new Refusal(409, 'not_held') : $locked->forceFill(['hidden_at' => null])->save(),
            };

            if ($decision === 'approve') {
                $this->answerReports($locked, $admin, Report::DISMISSED);
            }

            AuditLog::record('ideas.'.$decision, $locked, $admin, reason: $note !== '' ? $note : null, ip: $ip);
        });
    }

    /**
     * An appeal won (Notices): it shows again.
     */
    public function restore(Idea|IdeaVoice $subject, User $admin, string $note, ?string $ip): void
    {
        $subject instanceof IdeaVoice
            ? $this->decide($subject->idea, $subject, $admin, 'show', $note, $ip)
            : $this->decide($subject, null, $admin, 'show', $note, $ip);
    }

    private function hide(Idea $idea, User $admin, string $note): void
    {
        $copyright = Report::query()->whereMorphedTo('reportable', $idea)->where('status', Report::OPEN)->where('reason', 'copyright')->exists();
        $idea->forceFill(['hidden_at' => now(), 'held_at' => null])->save();
        // Its tune keeps the mark: shared again, it waits for review.
        Template::query()->whereKey($idea->template_id)->update(['idea_review_at' => now()]);
        $this->answerReports($idea, $admin, Report::UPHELD);
        $this->notices->give($idea->user, Notice::IDEA_HIDDEN, $idea, $copyright ? 'copyright' : 'community_guidelines', $note);
    }

    private function decideVoice(Idea $idea, IdeaVoice $voice, User $admin, string $decision, string $note, ?string $ip): void
    {
        $locked = IdeaVoice::query()->with('user')->whereKey($voice->id)->where('idea_id', $idea->id)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

        if ($decision === 'hide') {
            if ($locked->hidden_at === null) {
                $locked->forceFill(['hidden_at' => now()])->save();
                $this->notices->give($locked->user, Notice::IDEA_VOICE_HIDDEN, $locked, 'community_guidelines', $note);
            }
        } elseif ($locked->hidden_at === null) {
            throw new Refusal(409, 'not_held');
        } else {
            $locked->forceFill(['hidden_at' => null])->save();
        }

        $this->ideas->recount($idea);
        AuditLog::record('ideas.voice_'.$decision, $idea, $admin, after: ['voice' => $locked->uuid], reason: $note !== '' ? $note : null, ip: $ip);
    }

    private function answerReports(Idea $idea, User $admin, string $status): void
    {
        Report::query()->whereMorphedTo('reportable', $idea)->where('status', Report::OPEN)->update(['status' => $status, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
    }
}
