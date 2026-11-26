<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Site\SubmitToIndexNow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The hourly IndexNow batch, on the long worker (1 GB): reading every public page's path and date takes about 165 MB,
 * too much beside everything else on the scheduler's 512 MB (it restarted, 2026-11-25). The scheduler only queues it.
 */
final class SubmitIndexNowBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /** One batch at a time, for at most the hour. */
    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onConnection('database_long')->onQueue('long');
    }

    public function handle(SubmitToIndexNow $submit): void
    {
        $submit->handle();
    }
}
