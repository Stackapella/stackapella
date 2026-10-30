<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyScore;
use Illuminate\Console\Command;

/**
 * The day's boards are shown for a week; their scores are kept 30 days, then go.
 */
final class PruneDailyScores extends Command
{
    protected $signature = 'app:boards:prune-daily';

    protected $description = 'Delete daily-challenge scores older than 30 days';

    public function handle(): int
    {
        $deleted = DailyScore::query()->where('date', '<', now()->utc()->subDays(DailyScore::KEEP_DAYS)->toDateString())->delete();

        $this->info("Deleted {$deleted} old daily scores.");

        return self::SUCCESS;
    }
}
