<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Models\Project;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * bin.purge and bin.purgeTakes: "Delete now" and "Clear older takes" on the storage screen take
 * something out of the bin for good, at once, as the hourly purge (app:bin:purge) would 30 days on:
 * a tombstone so other devices hear, and its files deleted once the rows are gone. Only what's in
 * the bin, and only one's own: a project they own, takes they recorded.
 */
final class PurgeFromBin
{
    public function __construct(private readonly SyncSeq $seq) {}

    /**
     * A binned project, with its parts and takes.
     */
    public function project(User $user, string $uuid): void
    {
        $keys = DB::transaction(function () use ($user, $uuid): array {
            Owned::lockAccount($user);
            $project = Project::query()->where('uuid', $uuid)->where('user_id', $user->id)->whereNotNull('binned_at')->lockForUpdate()->first()
                ?? throw new Refusal(404, 'not_found');

            $keys = self::files(Take::query()->where('project_id', $project->id)->get(['file_key', 'upload_key']));
            $this->tombstone($user, 'project', $project->uuid);
            $project->delete();

            return $keys;
        });

        Storage::disk('private')->delete($keys);
    }

    /**
     * A binned take they recorded, outside a binned project (that goes with its project).
     */
    public function take(User $user, string $uuid): void
    {
        $keys = DB::transaction(function () use ($user, $uuid): array {
            Owned::lockAccount($user);
            $take = $this->binnedTakes($user)->where('takes.uuid', $uuid)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            return $this->purge($user, collect([$take]));
        });

        Storage::disk('private')->delete($keys);
    }

    /**
     * Every binned take they recorded on one project: the bytes freed.
     */
    public function olderTakes(User $user, string $projectUuid): int
    {
        [$bytes, $keys] = DB::transaction(function () use ($user, $projectUuid): array {
            Owned::lockAccount($user);
            $project = Project::query()->where('uuid', $projectUuid)->whereNull('binned_at')->first();

            // Theirs, or one they sing on (their own takes in it are theirs to clear).
            if ($project === null || ($project->user_id !== $user->id && ! Take::query()->where('project_id', $project->id)->where('user_id', $user->id)->exists())) {
                throw new Refusal(404, 'not_found');
            }

            $takes = $this->binnedTakes($user)->where('takes.project_id', $project->id)->lockForUpdate()->get();

            return [(int) $takes->sum(static fn (Take $take): int => $take->bytes()), $this->purge($user, $takes)];
        });

        Storage::disk('private')->delete($keys);

        return $bytes;
    }

    /**
     * Their takes in the bin that no part plays, in projects that aren't binned (bin.list's).
     *
     * @return Builder<Take>
     */
    private function binnedTakes(User $user): Builder
    {
        return Take::query()->select('takes.*')->where('takes.user_id', $user->id)->whereNotNull('takes.binned_at')
            ->whereHas('project', fn ($query) => $query->whereNull('binned_at'))
            ->whereDoesntHave('part', fn ($query) => $query->whereColumn('parts.current_take_id', 'takes.id'));
    }

    /**
     * @param  Collection<int, Take>  $takes
     * @return list<string> their files, to delete after the commit
     */
    private function purge(User $user, Collection $takes): array
    {
        foreach ($takes as $take) {
            $this->tombstone($user, 'take', $take->uuid);
            $take->delete();
        }

        return self::files($takes);
    }

    /**
     * @param  Collection<int, Take>  $takes
     * @return list<string>
     */
    private static function files(Collection $takes): array
    {
        return array_values($takes->flatMap(static fn (Take $take): array => array_filter([$take->file_key, $take->upload_key]))->all());
    }

    private function tombstone(User $user, string $kind, string $uuid): void
    {
        $tombstone = new SyncTombstone(['kind' => $kind, 'uuid' => $uuid]);
        $tombstone->forceFill(['user_id' => $user->id, 'seq' => $this->seq->next($user->id)])->save();
    }
}
