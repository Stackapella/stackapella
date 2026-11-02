<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Google\SearchConsoleClient;
use App\Support\Admin\Search;
use Illuminate\Console\Command;

/**
 * Daily: Search Console's last 28 days by page and by query, and the sitemaps' status
 * (docs/search-console.md). Quiet until SEARCH_CONSOLE_CREDENTIALS is set; a failure is kept for
 * the doctor and Admin > Growth > Search, and the last good numbers stay.
 */
final class SyncSearchConsole extends Command
{
    protected $signature = 'app:search-console:sync';

    protected $description = 'Fetch the last 28 days of Search Console numbers by page and by query';

    public function handle(SearchConsoleClient $client, Search $search): int
    {
        if (! $client->configured()) {
            $this->line('Search Console isn\'t set up (SEARCH_CONSOLE_CREDENTIALS); nothing to do.');

            return self::SUCCESS;
        }

        $error = $search->sync();

        if ($error !== null) {
            $this->warn('Search Console: '.$error);

            return self::SUCCESS;
        }

        $this->info('Search Console synced.');

        return self::SUCCESS;
    }
}
