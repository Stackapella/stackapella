<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Site\SubmitToIndexNow;
use Illuminate\Console\Command;

/**
 * One IndexNow batch (SubmitToIndexNow): the scheduler's, hourly, while Admin's `indexnow` switch is on.
 */
final class IndexNow extends Command
{
    protected $signature = 'app:indexnow';

    protected $description = 'Tell IndexNow about our public pages that are new or whose words changed (with the indexnow switch on)';

    public function handle(SubmitToIndexNow $submit): int
    {
        if (! $submit->on()) {
            $this->line('IndexNow is off (Admin > Switches, indexnow).');

            return self::SUCCESS;
        }

        $sent = $submit->handle();
        $this->line(count($sent).' sent to IndexNow.');

        return self::SUCCESS;
    }
}
