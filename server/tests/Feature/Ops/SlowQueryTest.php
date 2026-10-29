<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * A slow query is logged with its SQL and where it ran, never with its values.
 */
final class SlowQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_slow_query_is_logged_without_its_values(): void
    {
        Log::spy();
        config(['ops.slow.query_ms' => 5]);

        DB::select('SELECT SLEEP(?) AS slept, ? AS secret', [0.02, 'ada@example.com']);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Slow query'
            && str_contains($context['sql'], 'SLEEP(?)') && ! str_contains((string) json_encode($context), 'ada@example.com') && $context['ms'] >= 5)->once();
    }

    public function test_a_slow_query_in_a_job_names_the_job(): void
    {
        Log::spy();
        config(['ops.slow.query_ms' => 5, 'queue.default' => 'sync']);

        SlowQueryJob::dispatch();
        DB::select('SELECT SLEEP(?) AS slept', [0.02]);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Slow query' && $context['where'] === 'job '.SlowQueryJob::class)->once();
        // After the job, a query is no longer put down to it.
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Slow query' && $context['where'] !== 'job '.SlowQueryJob::class)->once();
    }
}

final class SlowQueryJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
        DB::select('SELECT SLEEP(?) AS slept', [0.02]);
    }
}
