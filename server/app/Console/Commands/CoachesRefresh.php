<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Coaching\CoachSearch;
use Illuminate\Console\Command;

/**
 * Hourly: each approved coach's lowest price, fastest review and next free lesson, for the
 * directory (N4).
 */
final class CoachesRefresh extends Command
{
    protected $signature = 'app:coaches:refresh';

    protected $description = 'Work out each coach\'s lowest price, fastest review and next lesson for the directory';

    public function handle(CoachSearch $search): int
    {
        $this->info('Refreshed '.$search->refreshAll().' coaches.');

        return self::SUCCESS;
    }
}
