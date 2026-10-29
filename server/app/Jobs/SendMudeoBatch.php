<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Mudeo\MudeoList;
use App\Models\MudeoBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Sends one batch of the mudeo email, one person at a time (docs/mudeo-transition-plan.md). On the
 * `long` queue: 50 emails can take longer than the default queue's minute.
 */
final class SendMudeoBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 280;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $batchId)
    {
        $this->onQueue('long');
    }

    public function handle(MudeoList $list): void
    {
        $batch = MudeoBatch::query()->find($this->batchId);

        if ($batch !== null && $batch->status === MudeoBatch::SENDING) {
            $list->deliver($batch);
        }
    }

    public function failed(?Throwable $e): void
    {
        $batch = MudeoBatch::query()->find($this->batchId);

        if ($batch !== null && $batch->status === MudeoBatch::SENDING) {
            app(MudeoList::class)->abandon($batch, $e?->getMessage());
        }
    }
}
