<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Actions\Notices\Notices;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\Notice;
use App\Models\Report;
use App\Models\User;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Support\Facades\DB;

/**
 * The trust and safety queue's actions on a shared harmony (docs/trust-and-safety-plan.md, "Admin
 * controls"), each audit-logged: hide it (a copyright notice, or Hillel's call), restore it
 * (which dismisses its reports), or remove it for good. Hiding or removing upholds the open
 * reports, and the owner drops to trust level 1 for `sharing.upheld_days`.
 */
final class ModerateHarmony
{
    public function __construct(private readonly HarmonyFiles $files, private readonly Notices $notices) {}

    public function hide(Harmony $harmony, User $admin, string $reason, string $note, ?string $ip): Harmony
    {
        DB::transaction(function () use ($harmony, $admin, $reason, $note, $ip): void {
            $locked = $this->lock($harmony);
            $before = $this->state($locked);

            if ($locked->removed_at === null) {
                // Hidden now, or hidden before by reports and now by a person: the owner is told why.
                if ($locked->hidden_at === null || $locked->hidden_reason === Harmony::HIDDEN_REPORTS) {
                    $this->tell($locked, Notice::HARMONY_HIDDEN, $reason, $note);
                }

                $locked->forceFill(['hidden_at' => now(), 'hidden_reason' => $reason])->save();
            }

            $this->uphold($locked, $admin);
            AuditLog::record('harmony.hidden', $locked, $admin, before: $before, after: $this->state($locked), reason: $note, ip: $ip);
        });

        $harmony->refresh();
        $this->files->sync($harmony);

        return $harmony;
    }

    public function restore(Harmony $harmony, User $admin, ?string $note, ?string $ip): Harmony
    {
        $released = DB::transaction(function () use ($harmony, $admin, $note, $ip): bool {
            $locked = $this->lock($harmony);
            $released = $locked->title_held_at !== null;

            if ($locked->removed_at !== null) {
                throw new Refusal(409, 'harmony_removed');
            }

            $before = $this->state($locked);
            $locked->forceFill(['hidden_at' => null, 'hidden_reason' => null, 'title_held_at' => null])->save();
            Report::query()->whereMorphedTo('reportable', $locked)->where('status', Report::OPEN)->update(['status' => Report::DISMISSED, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
            AuditLog::record('harmony.restored', $locked, $admin, before: $before, after: $this->state($locked), reason: $note, ip: $ip);
            $this->forgive($locked);

            return $released;
        });

        $harmony->refresh();
        // A released title goes on a new card.
        $released ? $this->files->redrawCard($harmony) : $this->files->sync($harmony);

        return $harmony;
    }

    public function remove(Harmony $harmony, User $admin, string $reason, string $note, ?string $ip): Harmony
    {
        DB::transaction(function () use ($harmony, $admin, $reason, $note, $ip): void {
            $locked = $this->lock($harmony);
            $before = $this->state($locked);

            if ($locked->removed_at === null) {
                $locked->forceFill(['removed_at' => now(), 'hidden_at' => $locked->hidden_at ?? now(), 'hidden_reason' => $reason])->save();
                $this->tell($locked, Notice::HARMONY_REMOVED, $reason, $note);
            }

            $this->uphold($locked, $admin);
            AuditLog::record('harmony.removed', $locked, $admin, before: $before, after: $this->state($locked), reason: $note, ip: $ip);
        });

        $harmony->refresh();
        $this->files->deleteAll($harmony);

        return $harmony;
    }

    /**
     * The statement of reasons to the owner (a copyright notice's rule, else the guidelines').
     */
    private function tell(Harmony $harmony, string $kind, string $reason, string $note): void
    {
        $owner = User::query()->find($harmony->user_id);

        if ($owner !== null) {
            $this->notices->give($owner, $kind, $harmony, $reason === Harmony::HIDDEN_DMCA ? 'copyright' : 'community_guidelines', $note);
        }
    }

    private function lock(Harmony $harmony): Harmony
    {
        return Harmony::query()->whereKey($harmony->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * The open reports are upheld, and the owner's trust drops to level 1 for a while.
     */
    private function uphold(Harmony $harmony, User $admin): void
    {
        Report::query()->whereMorphedTo('reportable', $harmony)->where('status', Report::OPEN)->update(['status' => Report::UPHELD, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);

        $until = now()->addDays((int) config('sharing.upheld_days'));
        User::query()->whereKey($harmony->user_id)->where(fn ($query) => $query->whereNull('trust_lowered_until')->orWhere('trust_lowered_until', '<', $until))->update(['trust_lowered_until' => $until]);
    }

    /**
     * A hide put right: the owner's lowered trust is lifted, unless another of their harmonies
     * was taken down in the same window (Hillel's own trust setting, an override, always wins).
     */
    private function forgive(Harmony $harmony): void
    {
        $since = now()->subDays((int) config('sharing.upheld_days'));
        $other = Harmony::query()->where('user_id', $harmony->user_id)->whereKeyNot($harmony->id)
            ->whereIn('hidden_reason', [Harmony::HIDDEN_DMCA, Harmony::HIDDEN_ADMIN])
            ->where('hidden_at', '>=', $since)
            ->exists();

        if (! $other) {
            User::query()->whereKey($harmony->user_id)->update(['trust_lowered_until' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Harmony $harmony): array
    {
        return ['hiddenReason' => $harmony->hidden_reason, 'removed' => $harmony->removed_at !== null, 'titleHeld' => $harmony->title_held_at !== null];
    }
}
