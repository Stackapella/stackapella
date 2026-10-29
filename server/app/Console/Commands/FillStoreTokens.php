<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\StoreSubscriptions;
use Illuminate\Console\Command;

/**
 * Gives a store token (billing.show's storeAccountToken) to any account made without one: those
 * made by the old code in a deploy's gap, between the migration and the new code.
 */
final class FillStoreTokens extends Command
{
    protected $signature = 'app:store:tokens';

    protected $description = 'Give every account its store token';

    public function handle(): int
    {
        $filled = StoreSubscriptions::fillTokens();

        if ($filled > 0) {
            $this->info("Gave {$filled} accounts their store token.");
        }

        return self::SUCCESS;
    }
}
