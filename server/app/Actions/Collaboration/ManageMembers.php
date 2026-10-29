<?php

declare(strict_types=1);

namespace App\Actions\Collaboration;

use App\Actions\Projects\Owned;
use App\Actions\Sharing\DeleteHarmony;
use App\Exceptions\Refusal;
use App\Models\Part;
use App\Models\ProjectMember;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use App\Support\Ids;
use App\Support\Sync\SyncSeq;
use Illuminate\Support\Facades\DB;

/**
 * Leaving a project, or the owner removing a singer (docs/backend-plan.md C7): their parts stay
 * (as the owner's) or go, as they choose. Gone parts go as parts.delete does: their takes to the
 * owner's bin, and a tombstone so every device drops them.
 */
final class ManageMembers
{
    private int $actor = 0;

    public function __construct(private readonly Owned $owned, private readonly SyncSeq $seq, private readonly DeleteHarmony $harmonies) {}

    public function remove(User $user, string $projectUuid, string $memberSqid, bool $keepParts): void
    {
        $project = $this->owned->reachable($user, $projectUuid);
        $memberId = Ids::decode($memberSqid);

        if ($memberId === $project->user_id) {
            throw new Refusal(403, 'owner_only');
        }

        // Only the owner removes someone else; a singer may only leave.
        if ($memberId !== $user->id && $project->user_id !== $user->id) {
            throw new Refusal(403, 'owner_only');
        }

        $this->actor = $user->id;

        $left = DB::transaction(function () use ($project, $memberId, $keepParts): bool {
            $owner = $project->user;
            Owned::lockAccount($owner);
            $member = ProjectMember::query()->where('project_id', $project->id)->where('user_id', $memberId ?? 0)->lockForUpdate()->first();

            if ($member === null) {
                return false;
            }

            foreach (Part::query()->where('project_id', $project->id)->where('created_by', $member->user_id)->lockForUpdate()->get() as $part) {
                if ($keepParts) {
                    $part->forceFill(['created_by' => null, 'version' => $part->version + 1, 'seq' => $this->seq->next($owner)])->save();

                    continue;
                }

                foreach (Take::query()->where('part_id', $part->id)->get() as $take) {
                    $take->forceFill(['binned_at' => $take->binned_at ?? now(), 'seq' => $this->seq->next($owner)])->save();
                }

                $tombstone = new SyncTombstone(['kind' => 'part', 'uuid' => $part->uuid]);
                $tombstone->forceFill(['user_id' => $owner->id, 'seq' => $this->seq->next($owner)])->save();
                $part->delete();
            }

            $member->delete();
            DB::table('project_exits')->upsert([['project_id' => $project->id, 'user_id' => $member->user_id, 'removed' => $member->user_id !== $this->actor, 'created_at' => now()]], ['project_id', 'user_id'], ['removed', 'created_at']);

            return true;
        });

        // Their parts went, so their voice goes from the project's shared harmonies too.
        if ($left && ! $keepParts && $memberId !== null) {
            $this->harmonies->withVoiceOf($memberId, $project->id);
        }
    }
}
