<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Books\Books;
use Illuminate\Console\Command;

/**
 * On the 1st (docs/invoice-ninja-integration-plan.md, section 2): last month's commission from
 * each coach, as one invoice each in Stackapella's own books.
 */
final class BooksCommission extends Command
{
    protected $signature = 'app:books:commission {--month= : YYYY-MM, last month by default}';

    protected $description = 'Record last month\'s coaching commission in Stackapella\'s Invoice Ninja';

    public function handle(Books $books): int
    {
        $month = is_string($this->option('month')) && preg_match('/^\d{4}-\d{2}$/', $this->option('month')) === 1 ? now()->setDateFrom($this->option('month').'-01')->startOfMonth() : now()->subMonthNoOverflow()->startOfMonth();
        $this->info("Noted {$books->commission($month)} coaches' commission for {$month->format('F Y')}.");

        return self::SUCCESS;
    }
}
