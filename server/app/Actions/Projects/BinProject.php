<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Models\Project;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;

/**
 * projects.delete and .restore: a deleted project waits 30 days in the bin with its parts and
 * takes (docs/backend-plan.md C3), then the purge removes it. A delete is versioned like a save,
 * so a late delete from another device can't throw away work it never saw.
 */
final class BinProject
{
    public function __construct(private readonly Owned $owned, private readonly Plans $plans, private readonly SyncSeq $seq) {}

    public function bin(User $user, string $uuid, int $version, string $changeId): void
    {
        DB::transaction(function () use ($user, $uuid, $version, $changeId): void {
            // The account row before the project's, the order every change takes (no deadlocks
            // with a singer saving at the same moment).
            Owned::lockAccount($user);
            $project = $this->owned->project($user, $uuid, lock: true);

            if ($project->binned_at !== null) {
                return;
            }

            if ($project->version !== $version && $project->last_change_id !== $changeId) {
                throw Owned::conflict($project);
            }

            $project->forceFill(['binned_at' => now(), 'last_change_id' => $changeId, 'seq' => $this->seq->next($user)])->save();

            // A slot freed (or taken) moves which projects are read-only: every device hears.
            if (! $this->plans->isPlus($user)) {
                $this->plans->readOnlyChanged($user);
            }
        });
    }

    public function restore(User $user, string $uuid): Project
    {
        return DB::transaction(function () use ($user, $uuid): Project {
            Owned::lockAccount($user);
            $project = $this->owned->project($user, $uuid, lock: true);

            if ($project->binned_at !== null) {
                if (! $this->plans->mayAddProject($user)) {
                    throw new Refusal(403, 'project_limit');
                }

                // Its takes count again once it's back.
                if ($this->plans->storageUsed($user) + $this->plans->projectBytes($project) > $this->plans->storageLimit($user)) {
                    throw new Refusal(403, 'storage_limit');
                }

                $project->forceFill(['binned_at' => null, 'seq' => $this->seq->next($user)])->save();

                if (! $this->plans->isPlus($user)) {
                    $this->plans->readOnlyChanged($user);
                }
            }

            return $project;
        });
    }
}
