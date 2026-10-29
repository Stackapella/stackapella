<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Models\Part;
use App\Models\Project;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Usage\Plans;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * parts.save: make or change a part, with the same versions and replays as a project. Pointing it
 * at another take sends the one it played to the bin once the new one is ready (so a replacement
 * that never arrives leaves the old one playing), and pointing it at a binned take brings that
 * one back: an offline Undo.
 */
final class SavePart
{
    public function __construct(private readonly Plans $plans, private readonly SyncSeq $seq, private readonly BinTakes $bin) {}

    /**
     * @param  array{changeId: string, projectId: string, version: int, order: int, name: string, color: string, volume: float|int, pan: float|int, muted: bool, solo: bool, nudgeFrames: int, trimStartFrames: int, trimEndFrames: int, templatePartKey?: string|null, kind?: string, currentTakeId?: string|null, settings?: array<string, mixed>, createdAt?: string|null}  $data
     */
    public function handle(User $user, string $uuid, array $data): Part
    {
        try {
            return DB::transaction(function () use ($user, $uuid, $data): Part {
                // Everything counts toward the project's owner (a singer on it included): their
                // account is locked first, as for any change that checks a limit.
                $ownerId = Project::query()->where('uuid', $data['projectId'])->value('user_id');

                if ($ownerId === null) {
                    throw new Refusal(409, 'project_missing');
                }

                $owner = User::query()->whereKey($ownerId)->firstOrFail();
                Owned::lockAccount($owner);
                $project = Project::query()->where('uuid', $data['projectId'])->lockForUpdate()->firstOrFail();
                $role = Owned::role($user, $project);

                if ($role === null) {
                    throw new Refusal(404, 'not_found');
                }

                Owned::live($project);

                $part = Part::query()->with('project')->where('uuid', $uuid)->lockForUpdate()->first();

                if ($part !== null) {
                    if (Owned::role($user, $part->project) === null) {
                        throw new Refusal(404, 'not_found');
                    }

                    if ($part->project_id !== $project->id) {
                        throw ValidationException::withMessages(['projectId' => __('validation.part_project')]);
                    }

                    Owned::mayChange($user, $part, $project);

                    if ($part->last_change_id === $data['changeId']) {
                        return $part->load('currentTake');
                    }

                    if ($data['version'] !== $part->version) {
                        throw Owned::conflict($part);
                    }
                } else {
                    // Deleted is deleted: a late save from another device doesn't bring it back.
                    if ($data['version'] !== 0 || SyncTombstone::query()->where('user_id', $owner->id)->where('kind', 'part')->where('uuid', $uuid)->exists()) {
                        throw new Refusal(409, 'part_gone');
                    }

                    $part = new Part(['device_created_at' => isset($data['createdAt']) ? Carbon::parse($data['createdAt'])->utc() : null]);
                    // A singer's new part is theirs; the owner's are the project's own.
                    $part->forceFill(['uuid' => $uuid, 'project_id' => $project->id, 'version' => 0, 'created_by' => $role === 'singer' ? $user->id : null]);
                }

                $this->plans->ensureEditable($owner, $project);

                $take = $this->chosenTake($owner, $part, $data['currentTakeId'] ?? null);
                $previous = $part->current_take_id;

                $part->fill([
                    'order' => $data['order'],
                    'name' => trim($data['name']),
                    'color' => strtolower($data['color']),
                    'volume' => $data['volume'],
                    'pan' => $data['pan'],
                    'muted' => $data['muted'],
                    'solo' => $data['solo'],
                    'nudge_frames' => $data['nudgeFrames'],
                    'trim_start_frames' => $data['trimStartFrames'],
                    'trim_end_frames' => $data['trimEndFrames'],
                    'template_part_key' => $data['templatePartKey'] ?? null,
                    'kind' => $data['kind'] ?? ($part->exists ? $part->kind : 'voice'),
                    'settings' => $data['settings'] ?? [],
                ]);
                $part->forceFill(['current_take_id' => $take?->id, 'version' => $part->version + 1, 'last_change_id' => $data['changeId'], 'seq' => $this->seq->next($owner)])->save();

                if ($previous !== null && $previous !== $take?->id) {
                    $this->bin->replaced($owner, $previous, $take);
                }

                // A singer's change moves the project up their list of shared projects too (only its
                // time: the project itself hasn't changed, so it isn't sent again).
                if ($role === 'singer') {
                    Project::query()->whereKey($project->id)->update(['updated_at' => now()]);
                }

                return $part->setRelation('project', $project)->load('currentTake');
            });
        } catch (UniqueConstraintViolationException) {
            $made = Part::query()->where('uuid', $uuid)->firstOrFail();

            if (Owned::role($user, $made->project) === null) {
                throw new Refusal(404, 'not_found');
            }

            if ($made->last_change_id === $data['changeId']) {
                return $made->load(['project', 'currentTake']);
            }

            throw Owned::conflict($made);
        }
    }

    /**
     * The take the part will play: one of its own (any status), taken out of the bin if it was
     * there, as long as it fits in the storage allowance.
     */
    private function chosenTake(User $user, Part $part, ?string $takeId): ?Take
    {
        if ($takeId === null) {
            return null;
        }

        $take = $part->exists ? Take::query()->where('uuid', $takeId)->where('part_id', $part->id)->lockForUpdate()->first() : null;

        if ($take === null) {
            throw ValidationException::withMessages(['currentTakeId' => __('validation.part_take')]);
        }

        if ($take->binned_at !== null && $take->id !== $part->current_take_id) {
            $current = $part->current_take_id !== null ? Take::query()->whereKey($part->current_take_id)->first() : null;

            if ($this->plans->storageUsed($user) - ($current !== null && $current->binned_at === null ? $current->bytes() : 0) + $take->bytes() > $this->plans->storageLimit($user)) {
                throw new Refusal(403, 'storage_limit');
            }

            $take->forceFill(['binned_at' => null, 'seq' => $this->seq->next($user)])->save();
        }

        return $take;
    }
}
