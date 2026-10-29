<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Harmony;
use App\Models\ShareVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sharing's housekeeping, hourly: a harmony whose upload was never completed goes after a day (with
 * whatever arrived); a video's file goes once its 7 days are up (`expired`: make it again); a
 * render stuck past any job's time limit is marked failed.
 */
final class PruneSharing extends Command
{
    protected $signature = 'app:sharing:prune';

    protected $description = 'Delete unfinished harmony uploads, expired share videos, and renders that never finished';

    public function handle(): int
    {
        $uploads = 0;
        Harmony::query()
            ->where('status', Harmony::UPLOADING)
            ->where('created_at', '<', now()->subDay())
            ->chunkById(200, function ($harmonies) use (&$uploads): void {
                foreach ($harmonies as $harmony) {
                    /** @var Harmony $harmony */
                    Storage::disk('private')->delete($harmony->upload_key);
                    $harmony->delete();
                    $uploads++;
                }
            });

        $videos = 0;
        ShareVideo::query()
            ->where('status', ShareVideo::READY)
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($expired) use (&$videos): void {
                foreach ($expired as $video) {
                    /** @var ShareVideo $video */
                    Storage::disk('private')->delete((string) $video->file_key);
                    $video->forceFill(['status' => ShareVideo::EXPIRED, 'file_key' => null])->save();
                    $videos++;
                }
            });

        $stuck = ShareVideo::query()
            ->where('status', ShareVideo::RENDERING)
            ->where('started_at', '<', now()->subMinutes(30))
            ->update(['status' => ShareVideo::FAILED, 'failure' => 'render_error', 'updated_at' => now()]);

        $this->info("Deleted {$uploads} unfinished uploads and {$videos} expired videos; {$stuck} stuck renders failed.");

        return self::SUCCESS;
    }
}
