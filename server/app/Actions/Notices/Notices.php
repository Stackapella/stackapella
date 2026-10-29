<?php

declare(strict_types=1);

namespace App\Actions\Notices;

use App\Actions\Admin\People\ManagePerson;
use App\Actions\Choirs\Choirs;
use App\Actions\Coaching\CoachSetup;
use App\Actions\Gallery\ModerateGallery;
use App\Actions\Ideas\ModerateIdeas;
use App\Actions\Sharing\ModerateHarmony;
use App\Actions\Templates\ModerateLibrary;
use App\Exceptions\Refusal;
use App\Mail\Account\NoticeDecided;
use App\Mail\Account\NoticeIssued;
use App\Models\AuditLog;
use App\Models\ChoirVoice;
use App\Models\CoachProfile;
use App\Models\Comment;
use App\Models\Harmony;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Notice;
use App\Models\Template;
use App\Models\User;
use App\Support\Ids;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Statements of reasons and their appeals (the DSA; docs/trust-and-safety-plan.md, "Legal
 * duties"): every restriction on someone's harmony, account or coaching makes a notice saying
 * what was done, why (the rule and Hillel's note) and whether it was automatic. It's in the app,
 * and by a private link emailed when the person can't sign in (a suspended account). "Ask for a
 * review" goes once, within 6 months, to Hillel, whose decision is recorded: kept, or reversed,
 * which undoes the restriction where it can.
 */
final class Notices
{
    public const RULES = ['community_guidelines', 'copyright', 'reports', 'terms', 'coach_agreement'];

    public function give(User $user, string $kind, ?Model $subject, string $rule, ?string $note, bool $automatic = false): Notice
    {
        $token = Str::random(40);
        $notice = new Notice(['kind' => $kind, 'rule' => $rule, 'note' => $note !== null && trim($note) !== '' ? trim($note) : null]);
        $notice->forceFill([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'automatic' => $automatic,
        ])->save();

        // A suspended account can't sign in to read it: the notice comes by email, with its link.
        if ($kind === Notice::ACCOUNT_SUSPENDED && $user->email !== null) {
            Mail::to($user->email)->queue((new NoticeIssued(__('notices.kinds.'.$kind), __('notices.rules.'.$rule), url('/notice?'.http_build_query(['token' => $token])), $notice->note))->afterCommit());
        }

        return $notice;
    }

    /**
     * A notice by its private link's token, or by its id for its own signed-in person.
     */
    public function find(string $key, ?User $viewer, bool $lock = false): Notice
    {
        $query = Notice::query()->when($lock, fn ($q) => $q->lockForUpdate());
        $notice = strlen($key) >= 32
            ? $query->where('token_hash', hash('sha256', $key))->first()
            : ($viewer !== null ? $query->whereKey(Ids::decode($key) ?? 0)->where('user_id', $viewer->id)->first() : null);

        return $notice ?? throw new Refusal(404, 'not_found');
    }

    public function appeal(string $key, ?User $viewer, string $text): Notice
    {
        return DB::transaction(function () use ($key, $viewer, $text): Notice {
            $notice = $this->find($key, $viewer, lock: true);

            if (! $notice->canAppeal()) {
                throw new Refusal(409, 'appeal_closed');
            }

            $notice->forceFill(['appeal_text' => trim($text), 'appealed_at' => now()])->save();

            return $notice;
        });
    }

    /**
     * Hillel's decision on an appeal: kept, or reversed (the restriction undone). The decision and
     * the undoing are one: a reversal that can't be undone is refused (`409 cannot_reverse`: a
     * removed harmony, or a later restriction on the same thing that still stands), never
     * recorded as reversed with the restriction left in place.
     */
    public function decide(User $admin, string $sqid, string $decision, string $note, ?string $ip): Notice
    {
        return DB::transaction(function () use ($admin, $sqid, $decision, $note, $ip): Notice {
            $notice = Notice::query()->whereKey(Ids::decode($sqid) ?? 0)->whereNotNull('appealed_at')->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            if ($notice->decided_at !== null) {
                throw new Refusal(409, 'appeal_decided');
            }

            if ($decision === Notice::REVERSED && ! $this->reversible($notice)) {
                throw new Refusal(409, 'cannot_reverse');
            }

            $notice->forceFill(['decision' => $decision, 'decision_note' => trim($note), 'decided_by' => $admin->id, 'decided_at' => now()])->save();
            AuditLog::record('notice.appeal_'.$decision, $notice->user, $admin, after: ['notice' => $notice->sqid(), 'kind' => $notice->kind], reason: $note, ip: $ip);

            if ($decision === Notice::REVERSED) {
                $this->undo($notice, $admin, $note, $ip);
            }

            // The answer to their request, by email: a suspended person can't sign in to see it.
            if ($notice->user?->email !== null) {
                Mail::to($notice->user->email)->queue((new NoticeDecided($decision, $note))->afterCommit());
            }

            return $notice;
        });
    }

    /**
     * Whether undoing this notice's restriction would undo only it: not a removed harmony, and no
     * later restriction on the same thing (a harmony, or the account and its coaching) that stands.
     */
    private function reversible(Notice $notice): bool
    {
        $subject = $notice->subject;

        if ($subject instanceof Harmony && $subject->removed_at !== null) {
            return false;
        }

        $same = match ($notice->kind) {
            Notice::HARMONY_HIDDEN, Notice::HARMONY_REMOVED => [Notice::HARMONY_HIDDEN, Notice::HARMONY_REMOVED],
            Notice::ACCOUNT_LIMITED, Notice::ACCOUNT_SUSPENDED => [Notice::ACCOUNT_LIMITED, Notice::ACCOUNT_SUSPENDED],
            default => [Notice::COACH_PAUSED, Notice::COACH_SUSPENDED],
        };

        return ! Notice::query()->where('user_id', $notice->user_id)->where('id', '>', $notice->id)->whereIn('kind', $same)
            ->when($subject instanceof Harmony, fn ($q) => $q->where('subject_type', $notice->subject_type)->where('subject_id', $notice->subject_id))
            ->where(fn ($q) => $q->whereNull('decision')->orWhere('decision', Notice::KEPT))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public static function view(Notice $notice, bool $admin = false): array
    {
        $subject = $notice->subject;

        return [
            'id' => $notice->sqid(),
            'kind' => $notice->kind,
            'what' => __('notices.kinds.'.$notice->kind),
            'about' => match (true) {
                $subject instanceof Harmony => ['kind' => 'harmony', 'id' => (string) $subject->public_id, 'title' => app(HarmonyFacts::class)->title($subject)],
                $subject instanceof CoachProfile => ['kind' => 'coaching', 'id' => '', 'title' => ''],
                default => ['kind' => 'account', 'id' => '', 'title' => ''],
            },
            'rule' => $notice->rule,
            'why' => __('notices.rules.'.$notice->rule),
            'note' => $notice->note,
            'automatic' => $notice->automatic,
            'createdAt' => $notice->created_at?->utc()->toIso8601ZuluString(),
            'canAppeal' => $notice->canAppeal(),
            'appeal' => $notice->appealed_at !== null ? [
                'text' => (string) $notice->appeal_text,
                'at' => $notice->appealed_at->utc()->toIso8601ZuluString(),
                'decision' => $notice->decision,
                'decisionNote' => $notice->decision_note,
                'decidedAt' => $notice->decided_at?->utc()->toIso8601ZuluString(),
            ] : null,
            ...($admin ? ['person' => ['id' => $notice->user->sqid(), 'name' => $notice->user->name]] : []),
        ];
    }

    private function undo(Notice $notice, User $admin, string $note, ?string $ip): void
    {
        $subject = $notice->subject;

        match ($notice->kind) {
            Notice::HARMONY_HIDDEN, Notice::HARMONY_REMOVED => $subject instanceof Harmony ? app(ModerateHarmony::class)->restore($subject, $admin, $note, $ip) : null,
            Notice::ACCOUNT_SUSPENDED => app(ManagePerson::class)->unsuspend($notice->user, $admin, $note, $ip),
            Notice::ACCOUNT_LIMITED => app(ManagePerson::class)->setTrust($notice->user, $admin, null, $note, $ip),
            Notice::COACH_PAUSED, Notice::COACH_SUSPENDED => app(CoachSetup::class)->setStatus($notice->user->sqid(), $admin, CoachProfile::APPROVED, $note, $ip),
            Notice::COMMENT_HIDDEN => $subject instanceof Comment ? app(ModerateGallery::class)->showComment($subject) : null,
            Notice::TEMPLATE_HIDDEN => $subject instanceof Template ? app(ModerateLibrary::class)->restore($subject, $admin, $note, $ip) : null,
            Notice::CHOIR_VOICE_REMOVED => $subject instanceof ChoirVoice ? app(Choirs::class)->restore($subject) : null,
            Notice::IDEA_HIDDEN, Notice::IDEA_VOICE_HIDDEN => $subject instanceof Idea || $subject instanceof IdeaVoice ? app(ModerateIdeas::class)->restore($subject, $admin, $note, $ip) : null,
            default => null,
        };
    }
}
