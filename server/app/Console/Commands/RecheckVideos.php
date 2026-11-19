<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Videos\RecheckSongVideos;
use Illuminate\Console\Command;

/**
 * Daily: re-checks synced recordings with YouTube within 30 days, and deletes what couldn't be (docs/youtube-sync-plan.md).
 */
final class RecheckVideos extends Command
{
    protected $signature = 'app:videos:recheck';

    protected $description = 'Re-check synced YouTube recordings and delete any not checked within 30 days';

    public function handle(RecheckSongVideos $recheck): int
    {
        $done = $recheck->handle();
        $this->info("Checked {$done['checked']}, hidden {$done['hidden']}, deleted {$done['deleted']}.");

        return self::SUCCESS;
    }
}
