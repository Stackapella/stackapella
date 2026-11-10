<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Outreach\AutoSendOutreach;
use Illuminate\Console\Command;

/**
 * Every 30 minutes on weekday mornings: one first outreach email, when Hillel's outreach_autosend
 * switch is on (App\Actions\Outreach\AutoSendOutreach).
 */
final class OutreachAutoSend extends Command
{
    protected $signature = 'app:outreach:autosend';

    protected $description = 'Send one first outreach email, when the outreach_autosend switch is on';

    public function handle(AutoSendOutreach $autoSend): int
    {
        $sent = $autoSend->handle();
        $this->info($sent !== null ? 'Sent the first email to organization '.$sent->outreach_target_id.'.' : 'Nothing sent.');

        return self::SUCCESS;
    }
}
