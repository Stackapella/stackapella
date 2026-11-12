<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;

/**
 * A date as each language writes it, in the page's language (the courses lead, 2026-11-12: Spanish says "6 de enero",
 * not "6 enero"). English keeps its own "6 January" and "3 November 2026".
 */
final class Dates
{
    /** @var array<string, string> a day and month, by language (English otherwise) */
    private const DAY_MONTH = ['es' => 'D [de] MMMM', 'de' => 'D. MMMM'];

    /** @var array<string, string> a day, month and year, by language (English otherwise) */
    private const DAY_MONTH_YEAR = ['es' => 'D [de] MMMM [de] YYYY', 'de' => 'D. MMMM YYYY'];

    /** @var array<string, string> a weekday, day and month, by language (English otherwise) */
    private const WEEKDAY_DAY_MONTH = ['es' => 'dddd D [de] MMMM', 'de' => 'dddd, D. MMMM'];

    public static function dayMonth(CarbonInterface $date): string
    {
        return self::format($date, self::DAY_MONTH[App::getLocale()] ?? 'D MMMM');
    }

    public static function dayMonthYear(CarbonInterface $date): string
    {
        return self::format($date, self::DAY_MONTH_YEAR[App::getLocale()] ?? 'D MMMM YYYY');
    }

    /** "Sunday 14 December", "domingo 14 de diciembre": the Jewish songs page's countdown (courses, 2026-11-12). */
    public static function weekdayDayMonth(CarbonInterface $date): string
    {
        return self::format($date, self::WEEKDAY_DAY_MONTH[App::getLocale()] ?? 'dddd D MMMM');
    }

    private static function format(CarbonInterface $date, string $format): string
    {
        return $date->copy()->locale(App::getLocale())->isoFormat($format);
    }
}
