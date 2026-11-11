<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Groups\TrackScores;
use Illuminate\Console\Command;

/**
 * Score files never put in a learning tracks request (or whose request has gone): deleted a day on,
 * rows and files (App\Actions\Groups\TrackScores::prune).
 */
final class PruneTrackScores extends Command
{
    protected $signature = 'app:track-scores:prune';

    protected $description = 'Delete score files sent for a learning tracks request that never came';

    public function handle(TrackScores $scores): int
    {
        $this->info('Deleted '.$scores->prune().' score files never put in a request.');

        return self::SUCCESS;
    }
}
