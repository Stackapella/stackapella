<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Books\Books;
use App\Models\InvoiceNinjaRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One row recorded in Invoice Ninja, tried 3 times with backoff; each try carries on from what the
 * last one made (Books::record). Still failing, it waits in the "not recorded" list for Retry.
 */
final class RecordInBooks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** Under the queue's retry_after (90): a row cut off is taken back by Books::sweep(). */
    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(public readonly int $recordId) {}

    public function handle(Books $books): void
    {
        $record = InvoiceNinjaRecord::query()->find($this->recordId);

        if ($record !== null) {
            $books->record($record);
        }
    }
}
