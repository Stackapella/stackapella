<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Models\IdeaVoice;
use App\Models\Project;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use App\Support\Usage\Plans;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The bin's purge (docs/backend-plan.md C3), run every hour:
 * - projects and takes binned over 30 days ago go for good, files and all, each leaving a
 *   tombstone so other devices hear of it. Each is checked again under a lock inside its own
 *   transaction (a restore a moment ago wins), and its files go only after the commit;
 * - a take no part plays is kept, not binned: every take of a part is listed and deletable
 *   (parts.takes.list, takes.delete; Hillel's "keep all takes"), so none uses storage unseen;
 * - an upload file goes after a day, and at once for a take past uploading (a second PUT to its
 *   URL); the row of one never completed waits for a retry;
 * - failed takes go after 30 days, tombstones after 90 (moving each person's "too old" mark).
 */
final class PurgeBin extends Command
{
    protected $signature = 'app:bin:purge';

    protected $description = 'Remove what has been in the bin for 30 days, orphaned takes, stale uploads and old tombstones';

    public function handle(SyncSeq $seq, Plans $plans): int
    {
        $before = now()->subDays(Take::BIN_DAYS);
        $capped = $this->capBins($seq, $plans);

        $projects = 0;
        foreach (Project::query()->whereNotNull('binned_at')->where('binned_at', '<', $before)->pluck('id') as $id) {
            $projects += $this->purgeProject((int) $id, $before, $seq) ? 1 : 0;
        }

        $takes = 0;
        $doomed = Take::query()->whereDoesntHave('part', fn ($query) => $query->whereColumn('parts.current_take_id', 'takes.id'))
            ->where(fn ($query) => $query->where(fn ($binned) => $binned->whereNotNull('binned_at')->where('binned_at', '<', $before))->orWhere(fn ($failed) => $failed->where('status', Take::FAILED)->where('updated_at', '<', $before)))
            ->pluck('id');

        foreach ($doomed as $id) {
            $takes += $this->purgeTake((int) $id, $before, $seq) ? 1 : 0;
        }

        $stale = $this->clearUploads() + $this->clearRecordings() + $this->clearVoices();
        $templates = $this->purgeTemplates();
        $tombstones = $this->pruneTombstones();

        $this->info("Purged {$projects} projects and {$takes} takes; removed {$stale} upload files, {$templates} deleted templates and {$tombstones} old tombstones; {$capped} bins over their allowance emptied from the oldest.");

        return self::SUCCESS;
    }

    private function clearUploads(): int
    {
        $count = 0;
        $disk = Storage::disk('private');

        // Never completed, a day on: the file goes, the row waits for a retry from the outbox.
        // Done (ready or failed) in the last two days: the copy it was made from goes. Never one
        // still processing: its job reads that copy (docs/security-review.md). A second PUT to the
        // original upload link is the bucket's rule's (uploads/ expire after a day). Deleted
        // without asking first (a missing key is no error; the bucket's own rule expires
        // uploads/ after a day as well).
        Take::query()->where(fn ($query) => $query->where(fn ($stuck) => $stuck->where('status', Take::UPLOADING)->where('created_at', '<', now()->subDay()))->orWhere(fn ($done) => $done->whereIn('status', [Take::READY, Take::FAILED])->where('updated_at', '>', now()->subDays(2))))
            ->select(['id', 'upload_key'])->chunkById(500, function ($takes) use ($disk, &$count): void {
                $disk->delete($takes->pluck('upload_key')->all());
                $count += $takes->count();
            });

        return $count;
    }

    /**
     * The creators' recorded guides (M7), as takes: one never completed goes after a day (its row
     * too, so it stops counting toward storage), a second PUT after one finished is thrown away,
     * and a failed one goes after 30 days.
     */
    /**
     * Voices on ideas, as recordings: one never completed goes after a day, a second PUT after one
     * finished is thrown away, and a failed one goes after 30 days.
     */
    private function clearVoices(): int
    {
        $count = 0;
        $disk = Storage::disk('private');

        foreach (IdeaVoice::query()->where('status', IdeaVoice::UPLOADING)->where('created_at', '<', now()->subDay())->get() as $stuck) {
            $disk->delete($stuck->upload_key);
            $stuck->delete();
            $count++;
        }

        $done = IdeaVoice::query()->whereIn('status', [IdeaVoice::READY, IdeaVoice::FAILED])->where('updated_at', '>', now()->subDays(2))->pluck('upload_key');
        $disk->delete($done->all());
        $count += $done->count();

        IdeaVoice::query()->where('status', IdeaVoice::FAILED)->where('updated_at', '<', now()->subDays(Take::BIN_DAYS))->delete();

        return $count;
    }

    private function clearRecordings(): int
    {
        $count = 0;
        $disk = Storage::disk('private');

        foreach (TemplateRecording::query()->where('status', TemplateRecording::UPLOADING)->where('created_at', '<', now()->subDay())->get() as $stuck) {
            $disk->delete($stuck->upload_key);
            $stuck->delete();
            $count++;
        }

        $done = TemplateRecording::query()->whereIn('status', [TemplateRecording::READY, TemplateRecording::FAILED])->where('updated_at', '>', now()->subDays(2))->pluck('upload_key');
        $disk->delete($done->all());
        $count += $done->count();

        TemplateRecording::query()->where('status', TemplateRecording::FAILED)->where('updated_at', '<', now()->subDays(Take::BIN_DAYS))->delete();

        return $count;
    }

    /**
     * A deleted template's versions are kept 30 days (projects already made from it keep their own
     * copy), then they go for good.
     */
    private function purgeTemplates(): int
    {
        $gone = Template::query()->whereNotNull('owner_id')->whereNotNull('deleted_at')->where('deleted_at', '<', now()->subDays(Take::BIN_DAYS));
        // Hear yourself as a choir's songs on them first: their private files go, their emptied rows stay (still counting).
        OwnChoirRequests::purgeForVersions(array_values(array_map('intval', TemplateVersion::query()->whereIn('template_id', (clone $gone)->select('id'))->pluck('id')->all())));

        return $gone->delete();
    }

    private function pruneTombstones(): int
    {
        $count = 0;

        SyncTombstone::query()->where('created_at', '<', now()->subDays(SyncTombstone::KEEP_DAYS))->chunkById(500, function ($rows) use (&$count): void {
            foreach ($rows->groupBy('user_id') as $userId => $group) {
                User::query()->whereKey($userId)->where('sync_pruned_seq', '<', $group->max('seq'))->update(['sync_pruned_seq' => $group->max('seq')]);
            }

            $count += SyncTombstone::query()->whereKey($rows->modelKeys())->delete();
        });

        return $count;
    }

    /**
     * The bin never counts toward storage (config/usage.php), so without a cap one account could fill it without end
     * (the abuse review, 2026-11-21): a bin holding more than the plan's storage allowance loses its oldest first, a
     * project with its takes or a take on its own, until it fits. Returns how many bins were over.
     */
    private function capBins(SyncSeq $seq, Plans $plans): int
    {
        $users = Take::query()->whereNotNull('binned_at')->distinct()->pluck('user_id')
            ->merge(Project::query()->whereNotNull('binned_at')->distinct()->pluck('user_id'))->unique()->values();
        $capped = 0;

        foreach (User::query()->with('subscriptions')->whereKey($users->all())->get() as $user) {
            $over = $plans->binBytes($user) - $plans->storageLimit($user);

            if ($over <= 0) {
                continue;
            }

            $capped++;
            $bytes = DB::raw('coalesce(sum(coalesce(takes.stored_bytes, takes.upload_bytes)), 0) as bytes');
            $projects = Project::query()->where('projects.user_id', $user->id)->whereNotNull('projects.binned_at')
                ->leftJoin('takes', fn ($join) => $join->on('takes.project_id', '=', 'projects.id')->where('takes.status', '!=', Take::FAILED))
                ->groupBy('projects.id', 'projects.binned_at')->get(['projects.id', 'projects.binned_at', $bytes])
                ->map(static fn ($p): array => ['project', (int) $p->id, (string) $p->binned_at, (int) $p->getAttribute('bytes')]);
            $loose = Take::query()->where('user_id', $user->id)->whereNotNull('binned_at')->where('status', '!=', Take::FAILED)
                ->whereHas('project', fn ($query) => $query->whereNull('binned_at'))
                ->get(['id', 'binned_at', 'stored_bytes', 'upload_bytes'])
                ->map(static fn (Take $take): array => ['take', $take->id, (string) $take->binned_at, (int) ($take->stored_bytes ?? $take->upload_bytes)]);

            foreach ($projects->concat($loose)->sortBy(static fn (array $item): string => $item[2].'#'.$item[1])->values() as [$kind, $id, , $size]) {
                if ($over <= 0) {
                    break;
                }

                if ($kind === 'project' ? $this->purgeProject($id, null, $seq) : $this->purgeTake($id, null, $seq)) {
                    $over -= $size;
                }
            }
        }

        return $capped;
    }

    /** A binned project, with its takes and their files: binned before $before, or any time (null). */
    private function purgeProject(int $id, ?Carbon $before, SyncSeq $seq): bool
    {
        $keys = DB::transaction(function () use ($id, $before, $seq): ?array {
            $project = Project::query()->whereKey($id)->whereNotNull('binned_at')->when($before !== null, fn ($query) => $query->where('binned_at', '<', $before))->lockForUpdate()->first();

            if ($project === null) {
                return null;
            }

            $keys = Take::query()->where('project_id', $project->id)->get(['file_key', 'upload_key'])->flatMap(fn (Take $take): array => array_filter([$take->file_key, $take->upload_key]))->values()->all();
            $this->tombstone($project->user_id, 'project', $project->uuid, $seq);
            $project->delete();

            return $keys;
        });

        if ($keys === null) {
            return false;
        }

        Storage::disk('private')->delete($keys);

        return true;
    }

    /**
     * A take no part has as its current one: binned before $before (or any time, null), or failed before $before.
     */
    private function purgeTake(int $id, ?Carbon $before, SyncSeq $seq): bool
    {
        $keys = DB::transaction(function () use ($id, $before, $seq): ?array {
            $take = Take::query()->whereKey($id)->lockForUpdate()->first();
            $current = $take !== null && DB::table('parts')->where('current_take_id', $take->id)->lockForUpdate()->exists();
            $binned = $take !== null && $take->binned_at !== null && ($before === null || $take->binned_at->lt($before));
            $failed = $take !== null && $before !== null && $take->status === Take::FAILED && $take->updated_at?->lt($before) === true;

            if ($take === null || $current || ! ($binned || $failed)) {
                return null;
            }

            $this->tombstone($take->user_id, 'take', $take->uuid, $seq);
            $take->delete();

            return array_values(array_filter([$take->file_key, $take->upload_key]));
        });

        if ($keys === null) {
            return false;
        }

        Storage::disk('private')->delete($keys);

        return true;
    }

    private function tombstone(int $userId, string $kind, string $uuid, SyncSeq $seq): void
    {
        $tombstone = new SyncTombstone(['kind' => $kind, 'uuid' => $uuid]);
        $tombstone->forceFill(['user_id' => $userId, 'seq' => $seq->next($userId)])->save();
    }
}
