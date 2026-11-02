<?php

declare(strict_types=1);

namespace App\Support\Stats;

use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * The day Admin's counts and analytics are kept and read in (config admin.report_timezone, from
 * ADMIN_REPORT_TIMEZONE; UTC by default): the day a count is filed under, today, finished days,
 * weeks and months. Timestamps in the database stay UTC: a moment in this zone is turned back into
 * UTC (utc()) before it's compared with one.
 */
final class ReportDay
{
    public static function zone(): string
    {
        $zone = (string) config('admin.report_timezone');

        return in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
    }

    /** Now, in the report's zone. */
    public static function now(): Carbon
    {
        return now()->setTimezone(self::zone());
    }

    /** Today's date in the report's zone, as Y-m-d. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** A Y-m-d date's start (or end) in the report's zone, as a UTC moment for the database. */
    public static function start(string $day): Carbon
    {
        return Carbon::parse($day, self::zone())->startOfDay()->utc();
    }

    public static function end(string $day): Carbon
    {
        return Carbon::parse($day, self::zone())->endOfDay()->utc();
    }
}
