<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ops\Readiness;
use App\Support\Ops\Releases;
use Illuminate\Console\Command;

/**
 * Every minute: proves the scheduler is alive (readiness fails when this is older than 3 minutes),
 * and notes the commit it runs the first time it runs it (Releases, for the growth data).
 */
final class Heartbeat extends Command
{
    protected $signature = 'app:heartbeat';

    protected $description = 'Record that the scheduler is running';

    public function handle(): int
    {
        Readiness::stamp(Readiness::HEARTBEAT);
        Releases::stamp();

        return self::SUCCESS;
    }
}
