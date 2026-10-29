<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Models\Part;
use App\Models\Take;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;

/**
 * takes.restore: Undo across devices. A binned take becomes its part's current one again, at the
 * nudge given (its own offset is where it was recorded); the take it replaces is kept (PartTakes).
 */
final class RestoreTake
{
    public function __construct(private readonly Owned $owned, private readonly Plans $plans, private readonly SyncSeq $seq, private readonly BinTakes $bin) {}

    public function handle(User $user, string $uuid, int $nudgeFrames): Part
    {
        return DB::transaction(function () use ($user, $uuid, $nudgeFrames): Part {
            $found = $this->owned->reachableTake($user, $uuid);
            // Everything counts toward the project's owner, whose account is locked first.
            $owner = User::query()->whereKey($found->user_id)->firstOrFail();
            Owned::lockAccount($owner);
            $take = Take::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

            if ($take->part_id === null) {
                throw new Refusal(409, 'part_gone');
            }

            $part = Part::query()->with('project')->whereKey($take->part_id)->lockForUpdate()->firstOrFail();
            Owned::mayChange($user, $part, $part->project);
            Owned::live($part->project);

            $this->plans->ensureEditable($owner, $part->project);

            if ($part->current_take_id === $take->id) {
                return $part->load('currentTake');
            }

            $current = $part->current_take_id !== null ? Take::query()->whereKey($part->current_take_id)->first() : null;

            if ($take->binned_at !== null && $this->plans->storageUsed($owner) - ($current !== null && $current->binned_at === null ? $current->bytes() : 0) + $take->bytes() > $this->plans->storageLimit($owner)) {
                throw new Refusal(403, 'storage_limit');
            }

            $take->forceFill(['binned_at' => null, 'seq' => $this->seq->next($owner)])->save();
            $part->forceFill(['current_take_id' => $take->id, 'nudge_frames' => $nudgeFrames, 'version' => $part->version + 1, 'seq' => $this->seq->next($owner)])->save();

            if ($current !== null) {
                $this->bin->replaced($owner, $current->id, $take);
            }

            return $part->load('currentTake');
        });
    }
}
