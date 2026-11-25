<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The scheduler runs on a 512 MB instance, and each task is a PHP process of 40 to 60 MB: it restarted three times on
 * 2026-11-24 and 25 when eight or nine started in the same minute. So no minute of a week holds more than two tasks
 * besides the minute's quick ones (routes/console.php's header).
 */
final class ScheduleSpreadTest extends TestCase
{
    /** Every minute, and light: the queue's drain, the heartbeat, the store's tokens, pushes and posts. */
    private const EVERY_MINUTE = ['queue:work', 'app:heartbeat', 'app:store:tokens', 'app:push:send', 'app:social:send'];

    private const MOST_A_MINUTE = 2;

    public function test_no_minute_of_a_week_starts_more_than_two_tasks_besides_the_quick_ones(): void
    {
        $events = array_values(array_filter(app(Schedule::class)->events(), static function (Event $event): bool {
            foreach (self::EVERY_MINUTE as $quick) {
                if (str_contains((string) $event->command, $quick)) {
                    return false;
                }
            }

            return true;
        }));
        $this->assertGreaterThan(20, count($events));

        $start = Carbon::parse('2026-11-23 00:00:00', 'UTC');
        $crowded = [];

        for ($minute = 0; $minute < 7 * 1440; $minute++) {
            $at = $start->copy()->addMinutes($minute);
            $due = array_filter($events, static fn (Event $event): bool => (new CronExpression($event->expression))->isDue($at->copy()->setTimezone($event->timezone ?? 'UTC')));

            if (count($due) > self::MOST_A_MINUTE) {
                $crowded[] = $at->format('D H:i').': '.implode(', ', array_map(static fn (Event $event): string => (string) $event->description, $due));
            }
        }

        $this->assertSame([], array_slice($crowded, 0, 10), 'Give each of these a minute of its own (routes/console.php).');
    }
}
