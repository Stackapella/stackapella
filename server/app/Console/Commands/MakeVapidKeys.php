<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Make the VAPID keys for web push, once: put them in VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY (the
 * private one encrypted). New keys end every device's subscription, so never make them again.
 */
final class MakeVapidKeys extends Command
{
    protected $signature = 'app:reminders:keys';

    protected $description = 'Make the VAPID key pair for practice reminders (once)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
