<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Models\Part;
use App\Models\Score;
use App\Models\Take;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Usage\Plans;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A part's takes, all kept (Hillel: "add an undo option to go back to the last take if it was
 * better. Maybe keep all takes so users can swap to try"): a new take no longer bins the one it
 * replaces (BinTakes), so the part keeps every take until one is deleted, each counting toward
 * storage. parts.takes.list lists them, parts.takes.use makes one current, takes.delete sends one
 * to Recently deleted (the bin, 30 days).
 */
final class PartTakes
{
    public function __construct(private readonly Owned $owned, private readonly Plans $plans, private readonly SyncSeq $seq) {}

    /**
     * Every kept take of a part on a project the person owns or sings in, newest first, with its
     * pitch score when its take sent one.
     *
     * @return array{part: Part, takes: Collection<int, Take>, pitch: array<string, int|float>}
     */
    public function list(User $user, string $partUuid): array
    {
        $part = $this->reachablePart($user, $partUuid);
        $takes = Take::query()->with('recorder')->where('part_id', $part->id)->whereNull('binned_at')->where('status', '!=', Take::FAILED)
            ->orderByDesc('created_at')->orderByDesc('id')->get();
        $pitch = Score::query()->whereIn('take_uuid', $takes->pluck('uuid'))->get(['take_uuid', 'summary'])
            ->mapWithKeys(static fn (Score $s): array => is_numeric($s->summary['pitchPercent'] ?? null) ? [(string) $s->take_uuid => $s->summary['pitchPercent'] + 0] : [])->all();

        return ['part' => $part, 'takes' => $takes, 'pitch' => $pitch];
    }

    /**
     * One of the part's kept takes becomes its current one, whole (the nudge and trims back to 0,
     * as a new take is); the take it replaces stays kept.
     */
    public function use(User $user, string $partUuid, string $takeUuid): Part
    {
        return DB::transaction(function () use ($user, $partUuid, $takeUuid): Part {
            $found = $this->reachablePart($user, $partUuid);
            $owner = User::query()->whereKey($found->project->user_id)->firstOrFail();
            Owned::lockAccount($owner);
            $part = Part::query()->with('project')->whereKey($found->id)->lockForUpdate()->firstOrFail();
            Owned::mayChange($user, $part, $part->project);
            Owned::live($part->project);
            $this->plans->ensureEditable($owner, $part->project);

            $take = Take::query()->where('uuid', $takeUuid)->where('part_id', $part->id)->whereNull('binned_at')->first() ?? throw new Refusal(404, 'not_found');

            if ($take->status !== Take::READY) {
                throw new Refusal(409, 'take_not_ready');
            }

            if ($part->current_take_id !== $take->id) {
                $part->forceFill(['current_take_id' => $take->id, 'nudge_frames' => 0, 'trim_start_frames' => 0, 'trim_end_frames' => 0, 'version' => $part->version + 1, 'seq' => $this->seq->next($owner)])->save();
            }

            return $part->load('currentTake');
        });
    }

    /**
     * A kept take goes to Recently deleted (restorable for 30 days); never the part's current one.
     * Already there, nothing changes.
     */
    public function delete(User $user, string $takeUuid): void
    {
        DB::transaction(function () use ($user, $takeUuid): void {
            $found = $this->owned->take($user, $takeUuid);
            $owner = User::query()->whereKey($found->user_id)->firstOrFail();
            Owned::lockAccount($owner);
            $take = Take::query()->with('part', 'project')->whereKey($found->id)->lockForUpdate()->firstOrFail();
            Owned::live($take->project);

            if ($take->binned_at !== null) {
                return;
            }

            if ($take->part !== null && $take->part->current_take_id === $take->id) {
                throw new Refusal(409, 'take_current');
            }

            $take->forceFill(['binned_at' => now(), 'seq' => $this->seq->next($owner)])->save();
        });
    }

    /**
     * A part on a project the person owns or sings in (anyone on a project hears every part).
     */
    private function reachablePart(User $user, string $uuid): Part
    {
        return Part::query()->with('project')->where('uuid', $uuid)
            ->whereHas('project', fn ($query) => $query->whereNull('binned_at')->where(fn ($q) => $q->where('user_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id))))
            ->first() ?? throw new Refusal(404, 'not_found');
    }
}
