<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Mudeo\MudeoList;
use Illuminate\Console\Command;

/**
 * The mudeo email's list goes 90 days after its last batch was sent, but for its opt-outs
 * (docs/mudeo-transition-plan.md, "What's kept, and for how long").
 */
final class PruneMudeoList extends Command
{
    protected $signature = 'app:mudeo:prune';

    protected $description = 'Delete the mudeo email list 90 days after its last batch (opt-outs stay)';

    public function handle(MudeoList $list): int
    {
        $this->info('Deleted '.$list->prune().' addresses from the mudeo list.');

        return self::SUCCESS;
    }
}
