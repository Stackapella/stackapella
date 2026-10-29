<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Push\AppPushes;
use Illuminate\Console\Command;

/**
 * Every minute: the pushes to the native apps that are due (AppPushes), while APP_PUSH_ENABLED.
 */
final class SendAppPushes extends Command
{
    protected $signature = 'app:push:send';

    protected $description = 'Send the due pushes to the native apps (APNs and FCM), and drop the stale ones';

    public function handle(AppPushes $pushes): int
    {
        $done = $pushes->sendDue();
        $this->info("Sent {$done['sent']}, dropped {$done['dropped']}, forgot {$done['gone']} gone tokens.");

        return self::SUCCESS;
    }
}
