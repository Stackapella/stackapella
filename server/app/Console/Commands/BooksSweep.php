<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Books\Books;
use App\Actions\Books\PlusInvoices;
use Illuminate\Console\Command;

/**
 * Hourly: recordings cut off or failed for a reason that passes are tried again, and sent Plus
 * invoices are checked with Invoice Ninja (a payment that completed later, or a lost webhook).
 */
final class BooksSweep extends Command
{
    protected $signature = 'app:books:sweep';

    protected $description = 'Retry Invoice Ninja recordings that can go through now, and check sent Plus invoices for payments';

    public function handle(PlusInvoices $invoices): int
    {
        $retried = Books::sweep();
        $paid = $invoices->sweep();
        $this->info("Tried {$retried} recordings again; {$paid} Plus invoices paid.");

        return self::SUCCESS;
    }
}
