<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Leagues\Leagues;
use Illuminate\Console\Command;

/**
 * Monday's leagues (N2): last week's close, the top 5 move up, and this week's are formed.
 */
final class WeeklyLeagues extends Command
{
    protected $signature = 'app:leagues:weekly';

    protected $description = 'Close last week\'s leagues, move the top 5 up, and form this week\'s';

    public function handle(Leagues $leagues): int
    {
        if (! $leagues->isOpen()) {
            $this->info('Leagues are closed.');

            return self::SUCCESS;
        }

        $this->info('Closed '.$leagues->weekly().' leagues, and formed this week\'s.');

        return self::SUCCESS;
    }
}
