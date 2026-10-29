<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ops\Readiness;
use Illuminate\Console\Command;

/**
 * Every minute: proves the scheduler is alive (readiness fails when this is older than 3 minutes).
 */
final class Heartbeat extends Command
{
    protected $signature = 'app:heartbeat';

    protected $description = 'Record that the scheduler is running';

    public function handle(): int
    {
        Readiness::stamp(Readiness::HEARTBEAT);

        return self::SUCCESS;
    }
}
