<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\StoreSubscriptions;
use Illuminate\Console\Command;

/**
 * Google refunds a Play purchase that isn't acknowledged within three days: the hourly backstop
 * for one whose acknowledgement failed and that no sync or notification has retried.
 */
final class AcknowledgeStorePurchases extends Command
{
    protected $signature = 'app:store:acknowledge';

    protected $description = 'Acknowledge Google Play purchases still waiting for it';

    public function handle(StoreSubscriptions $stores): int
    {
        $this->info('Acknowledged '.$stores->acknowledgePending().' Google Play purchases.');

        return self::SUCCESS;
    }
}
