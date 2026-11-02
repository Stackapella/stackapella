<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GitHub\CodeSearch;
use App\Support\CodeWatch\CodeWatch;
use Illuminate\Console\Command;

/**
 * Weekly: GitHub's code search for each marker string (docs/code-watch-plan.md). Quiet until the
 * markers and a token are set.
 */
final class SearchCodeWatch extends Command
{
    protected $signature = 'app:code-watch:search';

    protected $description = 'Search public code for our marker strings';

    public function handle(CodeSearch $search, CodeWatch $watch): int
    {
        if (! $search->configured() || (array) config('codewatch.markers') === []) {
            $this->line('Code watch isn\'t set up (CODE_WATCH_MARKERS, CODE_WATCH_GITHUB_TOKEN); nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Repositories seen: '.$watch->search());

        return self::SUCCESS;
    }
}
