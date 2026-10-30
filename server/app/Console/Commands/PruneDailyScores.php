<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyScore;
use App\Models\MonthlyScore;
use Illuminate\Console\Command;

/**
 * The day's boards are shown for a week; their scores are kept 30 days, then go. The month's
 * board keeps this month and last.
 */
final class PruneDailyScores extends Command
{
    protected $signature = 'app:boards:prune-daily';

    protected $description = 'Delete daily-challenge scores older than 30 days, and monthly bests before last month';

    public function handle(): int
    {
        $deleted = DailyScore::query()->where('date', '<', now()->utc()->subDays(DailyScore::KEEP_DAYS)->toDateString())->delete();

        $months = MonthlyScore::query()->where('month', '<', now()->utc()->startOfMonth()->subMonth()->format('Y-m'))->delete();

        $this->info("Deleted {$deleted} old daily scores and {$months} old monthly bests.");

        return self::SUCCESS;
    }
}
