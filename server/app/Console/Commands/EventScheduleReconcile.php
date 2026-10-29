<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Coaching\Lessons;
use Illuminate\Console\Command;

/**
 * Hourly (docs/event-schedule-integration-plan.md G2.6): every lesson from 2 days ago to 60 days
 * ahead is checked with Event Schedule, in case a webhook was lost. What ES has is applied; what
 * doesn't match goes to the integration's health screen (admin.eventSchedule.show).
 */
final class EventScheduleReconcile extends Command
{
    protected $signature = 'app:event-schedule:reconcile';

    protected $description = 'Check upcoming lessons against Event Schedule and fix what a lost webhook missed';

    public function handle(Lessons $lessons): int
    {
        $done = $lessons->reconcile();
        $this->info("Checked {$done['checked']}, fixed {$done['fixed']}, problems {$done['problems']}, skipped {$done['skipped']}.");

        return self::SUCCESS;
    }
}
