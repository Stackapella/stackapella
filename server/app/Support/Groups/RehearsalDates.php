<?php

declare(strict_types=1);

namespace App\Support\Groups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A group's rehearsal dates, worked out on read from the date its director set and whether it
 * repeats weekly. A day counts as passed only once it's over everywhere (12:00 UTC the next day),
 * as the server doesn't know the choir's time zone: so `next` never moves on early, and the app
 * may roll it on by the device's own date.
 */
final class RehearsalDates
{
    /**
     * @return array{date: string, weekly: bool, next: string|null, previous: string|null}|null
     */
    public static function of(?CarbonInterface $date, bool $weekly, CarbonInterface $now): ?array
    {
        if ($date === null) {
            return null;
        }

        $set = CarbonImmutable::parse($date->format('Y-m-d'), 'UTC');
        $today = CarbonImmutable::parse($now->copy()->utc()->subHours(12)->format('Y-m-d'), 'UTC');

        if ($set->gte($today)) {
            [$next, $previous] = [$set, null];
        } elseif ($weekly) {
            $next = $set->addWeeks((int) ceil($set->diffInDays($today) / 7));
            $previous = $next->subWeek();
        } else {
            [$next, $previous] = [null, $set];
        }

        return ['date' => $set->format('Y-m-d'), 'weekly' => $weekly, 'next' => $next?->format('Y-m-d'), 'previous' => $previous?->format('Y-m-d')];
    }
}
