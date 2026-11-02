<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * The scheduled tasks (the audit of 2026-11-01): each runs on one server only, so a deploy that
 * briefly runs two schedulers never does anything twice.
 */
final class SchedulerTest extends TestCase
{
    public function test_every_scheduled_task_runs_on_one_server_only(): void
    {
        $events = app(Schedule::class)->events();
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertTrue($event->onOneServer, ($event->description ?? $event->command ?? 'a task').' runs on every server');
        }
    }
}
