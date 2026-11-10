<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Support\Cache\RetryingDatabaseStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PDOException;
use Tests\TestCase;

/**
 * The database cache tries a write again when MySQL picks it as a deadlock's loser (Sentry
 * PHP-LARAVEL-4, 2026-11-10: the rate limiter's insert into `cache` on an admin page). No wrapping
 * transaction here, as the store never retries inside one; the keys are cleared after.
 */
final class CacheDeadlockTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $connectionsToTransact = [];

    private int $deadlocks = 0;

    private int $seen = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('database')->flush();
        // The next $deadlocks inserts into the cache lose a deadlock, as MySQL reports it.
        DB::beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'insert ignore into `cache`')) {
                $this->seen++;

                if ($this->deadlocks > 0) {
                    $this->deadlocks--;

                    throw new QueryException('mysql', $sql, [], new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'));
                }
            }
        });
    }

    protected function tearDown(): void
    {
        Cache::store('database')->flush();

        parent::tearDown();
    }

    public function test_the_database_store_is_the_retrying_one(): void
    {
        $this->assertInstanceOf(RetryingDatabaseStore::class, Cache::store('database')->getStore());
    }

    public function test_a_write_that_loses_a_deadlock_is_tried_again(): void
    {
        $this->deadlocks = 2;

        $this->assertSame(1, RateLimiter::hit('deadlock-test', 60));
        $this->assertSame(2, RateLimiter::hit('deadlock-test', 60));
        $this->assertSame(0, $this->deadlocks);
    }

    public function test_it_gives_up_after_three_tries_and_never_retries_inside_a_transaction(): void
    {
        $this->deadlocks = 3;
        $this->seen = 0;
        try {
            Cache::store('database')->add('deadlock-test', 1, 60);
            $this->fail('A third deadlock should have been thrown.');
        } catch (QueryException) {
            $this->assertSame(3, $this->seen);
        }

        $this->deadlocks = 1;
        $this->seen = 0;
        DB::beginTransaction();
        try {
            Cache::store('database')->add('deadlock-test-2', 1, 60);
            $this->fail('A deadlock inside a transaction is for that transaction to retry.');
        } catch (QueryException) {
            $this->assertSame(1, $this->seen);
        } finally {
            DB::rollBack();
        }
    }
}
