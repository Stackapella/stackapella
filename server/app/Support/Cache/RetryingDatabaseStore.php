<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Closure;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;

/**
 * The database cache, with a write that loses a deadlock tried again (2026-11-10: two of the admin
 * page's requests at once deadlocked on the rate limiter's `insert ignore` into `cache`, and one
 * failed). Only the writes the rate limiter and Cache::add make, only on MySQL's deadlock or lock
 * wait, and never inside an outer transaction (that one was rolled back whole: it's for its own
 * retry). config/cache.php's `database` store uses it.
 */
final class RetryingDatabaseStore extends DatabaseStore
{
    use DetectsConcurrencyErrors;

    private const TRIES = 3;

    public function add($key, $value, $seconds): bool
    {
        return $this->retrying(fn (): bool => parent::add($key, $value, $seconds));
    }

    public function increment($key, $value = 1): int|false
    {
        return $this->retrying(fn (): int|false => parent::increment($key, $value));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    private function retrying(Closure $write): mixed
    {
        for ($try = 1; ; $try++) {
            try {
                return $write();
            } catch (QueryException $e) {
                if ($try >= self::TRIES || $this->getConnection()->transactionLevel() > 0 || ! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }

                // A moment apart, so the two don't meet again.
                usleep(random_int(5_000, 25_000) * $try);
            }
        }
    }
}
