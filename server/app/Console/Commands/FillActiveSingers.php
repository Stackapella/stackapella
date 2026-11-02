<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Counts\ActiveSingers;
use Illuminate\Console\Command;

/**
 * Keeps each finished day's active singers (docs/admin-analytics-plan.md, slice 1). Hourly, so a
 * deploy's first run fills the last 24 weeks soon after, and a missed night is filled the next hour.
 */
final class FillActiveSingers extends Command
{
    protected $signature = 'app:counts:active-singers';

    protected $description = 'Keep each finished day\'s count of active singers (7 and 30 days)';

    public function handle(ActiveSingers $active): int
    {
        $this->info('Filled '.$active->fill().' days.');

        return self::SUCCESS;
    }
}
