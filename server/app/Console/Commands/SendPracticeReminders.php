<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Reminders\PracticeReminders;
use Illuminate\Console\Command;

/**
 * Every 15 minutes: the practice reminders that are due (docs/operations-plan.md).
 */
final class SendPracticeReminders extends Command
{
    protected $signature = 'app:reminders:send';

    protected $description = 'Send the practice reminders that are due';

    public function handle(PracticeReminders $reminders): int
    {
        $this->info('Sent '.$reminders->sendDue(now()).' reminders.');

        return self::SUCCESS;
    }
}
