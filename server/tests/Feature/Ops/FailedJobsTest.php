<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Failed jobs go after 30 days (docs/privacy-audit.md): their payloads name people and things.
 */
final class FailedJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_jobs_older_than_30_days_are_pruned_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(static fn (Event $event): bool => $event->description === 'prune-failed-jobs');
        $this->assertNotNull($event);
        $this->assertStringContainsString('--hours=720', (string) $event->command);

        foreach ([31, 29] as $days) {
            DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays($days)]);
        }

        $this->artisan('queue:prune-failed --hours=720')->assertSuccessful();

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }
}
