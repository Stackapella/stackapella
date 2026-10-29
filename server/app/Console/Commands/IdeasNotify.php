<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ideas\TellWriters;
use Illuminate\Console\Command;

/**
 * Hourly: each idea's writer hears of its new voices, at most once a day (TellWriters).
 */
final class IdeasNotify extends Command
{
    protected $signature = 'app:ideas:notify';

    protected $description = 'Email writers about the new voices on their ideas, at most once a day each';

    public function handle(TellWriters $tell): int
    {
        $this->info('Emailed '.$tell->handle().' writers.');

        return self::SUCCESS;
    }
}
