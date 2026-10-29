<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Backups\DatabaseDump;
use App\Support\Ops\Readiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Backups to the `backup` bucket (docs/backend-plan.md A3; a port of iaw's BackupRun):
 * - nightly: a gzipped mysqldump, 30 daily and 12 monthly copies kept;
 * - --files (weekly): every file in `media` and `private` not yet copied (keys never change, so a
 *   key already there is the same file). The buckets' own versioning keeps 30 days of old versions.
 *   A copy whose file was deleted goes too, 30 days after it was first found missing, so a deleted
 *   account's recordings don't outlive the promise in the privacy policy. Exports (data downloads,
 *   share videos) are never copied: they last 7 days and can be made again.
 */
final class BackupRun extends Command
{
    protected $signature = 'app:backup:run {--files : Copy new files from media and private instead of dumping the database}';

    protected $description = 'Back up the database (and weekly, the files) to the backup bucket';

    public function handle(DatabaseDump $dump): int
    {
        return $this->option('files') ? $this->files() : $this->database($dump);
    }

    private function database(DatabaseDump $dump): int
    {
        $name = 'db/daily/'.now()->format('Y-m-d-His').'.sql.gz';
        $local = storage_path('app/'.Str::random(12).'.sql.gz');
        $size = 0;

        try {
            $dump->dump($local);
            $size = (int) filesize($local);
            $stream = fopen($local, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Couldn't open the dump at {$local}.");
            }

            Storage::disk('backup')->writeStream($name, $stream);
            is_resource($stream) && fclose($stream);

            if (now()->day === 1 || Storage::disk('backup')->files('db/monthly') === []) {
                Storage::disk('backup')->copy($name, 'db/monthly/'.now()->format('Y-m').'.sql.gz');
            }
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($local);
        }

        $this->prune('db/daily', 30);
        $this->prune('db/monthly', 12);
        Readiness::stamp(Readiness::BACKUP, ['at' => now()->utc()->toIso8601ZuluString(), 'file' => $name, 'bytes' => $size]);
        $this->info("Saved {$name} (".number_format($size / 1048576, 1).' MB).');

        return self::SUCCESS;
    }

    /**
     * When each copy whose file is gone was first found missing (in the backup bucket itself).
     */
    public const MISSING = 'state/missing.json';

    /**
     * Days a deleted file's copy is kept, matching the buckets' old versions.
     */
    public const KEEP_DELETED_DAYS = 30;

    private function files(): int
    {
        $copied = 0;
        $removed = 0;
        $backup = Storage::disk('backup');
        $missing = (array) json_decode((string) ($backup->exists(self::MISSING) ? $backup->get(self::MISSING) : '[]'), true);
        $today = now()->utc()->toDateString();

        foreach (['media', 'private'] as $disk) {
            $existing = array_flip($backup->allFiles($disk));
            $sources = [];

            foreach (Storage::disk($disk)->allFiles() as $key) {
                if (! self::backedUp($key)) {
                    continue;
                }

                $sources[$disk.'/'.$key] = true;

                if (isset($existing[$disk.'/'.$key])) {
                    continue;
                }

                $stream = Storage::disk($disk)->readStream($key);

                if ($stream === null) {
                    $this->warn("Couldn't read {$disk}/{$key}; it was skipped.");

                    continue;
                }

                $backup->writeStream($disk.'/'.$key, $stream);
                is_resource($stream) && fclose($stream);
                $copied++;
            }

            foreach (array_keys($existing) as $copy) {
                $copy = (string) $copy;

                if (isset($sources[$copy])) {
                    unset($missing[$copy]);

                    continue;
                }

                // Never meant to be here (an export copied before this rule): it goes at once.
                $since = self::backedUp(substr($copy, strlen($disk) + 1)) ? (string) ($missing[$copy] ??= $today) : '0000-01-01';

                if ($since <= now()->utc()->subDays(self::KEEP_DELETED_DAYS)->toDateString()) {
                    $backup->delete($copy);
                    unset($missing[$copy]);
                    $removed++;
                }
            }
        }

        $backup->put(self::MISSING, (string) json_encode($missing));
        Readiness::stamp(Readiness::BACKUP.'_files', ['at' => now()->utc()->toIso8601ZuluString(), 'copied' => $copied, 'removed' => $removed]);
        $this->info("Copied {$copied} new files, and removed {$removed} copies of files deleted ".self::KEEP_DELETED_DAYS.' days ago.');

        return self::SUCCESS;
    }

    /**
     * tmp/ is scratch; uploads/ waits to become a take or is purged; exports/ (data downloads and
     * share videos) lasts 7 days.
     */
    private static function backedUp(string $key): bool
    {
        return ! str_starts_with($key, 'tmp/') && ! str_starts_with($key, 'uploads/') && ! str_starts_with($key, 'exports/');
    }

    private function prune(string $folder, int $keep): void
    {
        $files = collect(Storage::disk('backup')->files($folder))->sort()->values();

        foreach ($files->slice(0, max(0, $files->count() - $keep)) as $old) {
            Storage::disk('backup')->delete($old);
        }
    }
}
