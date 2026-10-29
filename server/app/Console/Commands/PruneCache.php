<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Expired rows of the database cache. Laravel deletes one only when its key is read again, and a
 * limiter's key per IP or address rarely is, so the table (and the nightly dump) would only grow.
 * In batches, so the table is never locked for long.
 */
final class PruneCache extends Command
{
    protected $signature = 'app:cache:prune';

    protected $description = 'Delete expired rows from the database cache and its locks';

    private const BATCH = 5000;

    public function handle(): int
    {
        $store = (string) config('cache.default');

        if (config("cache.stores.{$store}.driver") !== 'database') {
            $this->info('The cache isn\'t the database: nothing to prune.');

            return self::SUCCESS;
        }

        $connection = DB::connection(config("cache.stores.{$store}.connection"));
        $deleted = 0;

        foreach ([(string) config("cache.stores.{$store}.table", 'cache'), (string) (config("cache.stores.{$store}.lock_table") ?? 'cache_locks')] as $table) {
            do {
                $batch = $connection->table($table)->where('expiration', '<', now()->getTimestamp())->limit(self::BATCH)->delete();
                $deleted += $batch;
            } while ($batch === self::BATCH);
        }

        $this->info("Deleted {$deleted} expired cache rows.");

        return self::SUCCESS;
    }
}
