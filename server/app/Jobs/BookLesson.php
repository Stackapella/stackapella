<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Coaching\Lessons;
use App\Models\Engagement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * A paid lesson booked in Event Schedule, tried for about 10 minutes (the same idempotency key
 * each time, so ES books it once). When it gives up the singer is refunded, unless ES has it.
 */
final class BookLesson implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 6;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public int $uniqueFor = 900;

    public function __construct(public readonly int $engagementId) {}

    public function uniqueId(): string
    {
        return (string) $this->engagementId;
    }

    public function handle(Lessons $lessons): void
    {
        $engagement = Engagement::query()->with('singer')->find($this->engagementId);

        if ($engagement !== null) {
            $lessons->book($engagement);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $engagement = Engagement::query()->find($this->engagementId);

        if ($engagement !== null) {
            app(Lessons::class)->bookingFailed($engagement, $exception);
        }
    }
}
