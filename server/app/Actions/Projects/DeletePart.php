<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Part;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;

/**
 * parts.delete: the part goes, and its takes wait in the bin (they can't come back without it).
 * Versioned like a save; deleting one that's already gone is done.
 */
final class DeletePart
{
    public function __construct(private readonly SyncSeq $seq, private readonly Plans $plans) {}

    public function handle(User $user, string $uuid, int $version, string $changeId): void
    {
        // The owner's account row first, the order every change takes (no deadlocks between the
        // owner and a singer).
        $ownerId = Part::query()->join('projects', 'projects.id', '=', 'parts.project_id')->where('parts.uuid', $uuid)->value('projects.user_id');

        DB::transaction(function () use ($user, $uuid, $version, $changeId, $ownerId): void {
            if ($ownerId !== null) {
                User::query()->whereKey($ownerId)->lockForUpdate()->first(['id']);
            }

            // The owner's part, or a singer's own: someone else's on a shared project is refused.
            $part = Part::query()->with('project.user')->where('uuid', $uuid)->whereHas('project', fn ($query) => $query->where('user_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))->lockForUpdate()->first();

            if ($part === null) {
                return;
            }

            $owner = $part->project->user;
            Owned::mayChange($user, $part, $part->project);
            Owned::live($part->project);
            $this->plans->ensureEditable($owner, $part->project);

            if ($part->version !== $version && $part->last_change_id !== $changeId) {
                throw Owned::conflict($part);
            }

            foreach (Take::query()->where('part_id', $part->id)->get() as $take) {
                $take->forceFill(['binned_at' => $take->binned_at ?? now(), 'seq' => $this->seq->next($owner)])->save();
            }

            $tombstone = new SyncTombstone(['kind' => 'part', 'uuid' => $part->uuid]);
            $tombstone->forceFill(['user_id' => $owner->id, 'seq' => $this->seq->next($owner)])->save();

            $part->delete();
        });
    }
}
