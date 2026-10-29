<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Coaching\Engagements;
use App\Actions\Coaching\Lessons;
use Illuminate\Console\Command;

/**
 * Coaching's clock (docs/coaching-marketplace-plan.md F3), every five minutes: reviews not accepted
 * within 48 hours, or not delivered in time, are refunded in full; a delivered review's share moves
 * to the coach once Unsend has passed; a Checkout never finished is let go after a day. For lessons
 * (M9): one never approved in time is cancelled and refunded, one a day past its end counts as
 * done, a share that couldn't move is tried again, and a booking whose job was lost is queued again.
 */
final class CoachingDeadlines extends Command
{
    protected $signature = 'app:coaching:deadlines';

    protected $description = 'Refund late reviews and lessons, pay coaches for what was done, and drop abandoned checkouts';

    public function handle(Engagements $engagements, Lessons $lessons): int
    {
        $done = $engagements->deadlines();
        $this->info("Refunded {$done['refunded']}, released {$done['released']}, let go {$done['abandoned']}.");

        $lesson = $lessons->deadlines();
        $this->info("Lessons: refunded {$lesson['refunded']}, completed {$lesson['completed']}, transferred {$lesson['transferred']}, queued again {$lesson['requeued']}.");

        return self::SUCCESS;
    }
}
