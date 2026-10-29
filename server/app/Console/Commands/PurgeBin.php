<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdeaVoice;
use App\Models\Project;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use Illuminate\Console\Command;
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

    public function handle(SyncSeq $seq): int
    {
        $before = now()->subDays(Take::BIN_DAYS);

        $projects = 0;
        foreach (Project::query()->whereNotNull('binned_at')->where('binned_at', '<', $before)->pluck('id') as $id) {
            $keys = DB::transaction(function () use ($id, $before, $seq): ?array {
                $project = Project::query()->whereKey($id)->whereNotNull('binned_at')->where('binned_at', '<', $before)->lockForUpdate()->first();

                if ($project === null) {
                    return null;
                }

                $keys = Take::query()->where('project_id', $project->id)->get(['file_key', 'upload_key'])->flatMap(fn (Take $take): array => array_filter([$take->file_key, $take->upload_key]))->values()->all();
                $this->tombstone($project->user_id, 'project', $project->uuid, $seq);
                $project->delete();

                return $keys;
            });

            if ($keys !== null) {
                Storage::disk('private')->delete($keys);
                $projects++;
            }
        }

        $takes = 0;
        $doomed = Take::query()->whereDoesntHave('part', fn ($query) => $query->whereColumn('parts.current_take_id', 'takes.id'))
            ->where(fn ($query) => $query->where(fn ($binned) => $binned->whereNotNull('binned_at')->where('binned_at', '<', $before))->orWhere(fn ($failed) => $failed->where('status', Take::FAILED)->where('updated_at', '<', $before)))
            ->pluck('id');

        foreach ($doomed as $id) {
            $keys = DB::transaction(function () use ($id, $before, $seq): ?array {
                $take = Take::query()->whereKey($id)->lockForUpdate()->first();
                $current = $take !== null && DB::table('parts')->where('current_take_id', $take->id)->lockForUpdate()->exists();
                $due = $take !== null && (($take->binned_at !== null && $take->binned_at->lt($before)) || ($take->status === Take::FAILED && $take->updated_at?->lt($before)));

                if ($take === null || $current || ! $due) {
                    return null;
                }

                $this->tombstone($take->user_id, 'take', $take->uuid, $seq);
                $take->delete();

                return array_values(array_filter([$take->file_key, $take->upload_key]));
            });

            if ($keys !== null) {
                Storage::disk('private')->delete($keys);
                $takes++;
            }
        }

        $stale = $this->clearUploads() + $this->clearRecordings() + $this->clearVoices();
        $templates = $this->purgeTemplates();
        $tombstones = $this->pruneTombstones();

        $this->info("Purged {$projects} projects and {$takes} takes; removed {$stale} upload files, {$templates} deleted templates and {$tombstones} old tombstones.");

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
        return Template::query()->whereNotNull('owner_id')->whereNotNull('deleted_at')->where('deleted_at', '<', now()->subDays(Take::BIN_DAYS))->delete();
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

    private function tombstone(int $userId, string $kind, string $uuid, SyncSeq $seq): void
    {
        $tombstone = new SyncTombstone(['kind' => $kind, 'uuid' => $uuid]);
        $tombstone->forceFill(['user_id' => $userId, 'seq' => $seq->next($userId)])->save();
    }
}
