<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Books\Books;
use App\Actions\Books\Ledger;
use App\Models\InvoiceNinjaConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A coach's last 12 months of sales into their Invoice Ninja, once, when they first connect.
 */
final class BackfillBooks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $connectionId) {}

    public function handle(Books $books, Ledger $ledger): void
    {
        $connection = InvoiceNinjaConnection::query()->find($this->connectionId);

        if ($connection !== null) {
            $books->backfill($connection, $ledger);
        }
    }
}
