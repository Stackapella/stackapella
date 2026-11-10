<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The hourly counts' retention (docs/growth-compare-plan.md, 5.2): kept 100 days, enough for the
 * furthest exact cut any Growth view needs (12 weeks back); older days fall back to an estimate from
 * their daily total, which stays forever.
 */
final class PruneHourlyCounts extends Command
{
    public const KEEP_DAYS = 100;

    protected $signature = 'app:counts:prune-hours';

    protected $description = 'Delete hourly counts older than 100 days (the daily counts stay)';

    public function handle(): int
    {
        $before = now()->utc()->startOfHour()->subDays(self::KEEP_DAYS)->format('Y-m-d H:i:s');
        $deleted = 0;

        // In batches, so a first run after a long gap never holds the table.
        do {
            $batch = DB::table('usage_count_hours')->where('hour', '<', $before)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} hourly counts older than ".self::KEEP_DAYS.' days.');

        return self::SUCCESS;
    }
}
