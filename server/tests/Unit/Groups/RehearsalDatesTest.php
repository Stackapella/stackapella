<?php

declare(strict_types=1);

namespace Tests\Unit\Groups;

use App\Support\Groups\RehearsalDates;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A group's next and previous rehearsal, from the date its director set: a day is passed only once
 * it's over everywhere (12:00 UTC the next day), and a weekly one rolls on in 7-day steps.
 */
final class RehearsalDatesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool, 2: string, 3: string|null, 4: string|null}>
     */
    public static function cases(): array
    {
        return [
            'ahead' => ['2026-11-12', true, '2026-11-10T09:00:00Z', '2026-11-12', null],
            'on the day' => ['2026-11-12', false, '2026-11-12T20:00:00Z', '2026-11-12', null],
            'still the day somewhere' => ['2026-11-12', true, '2026-11-13T11:59:00Z', '2026-11-12', null],
            'over everywhere, weekly' => ['2026-11-12', true, '2026-11-13T12:00:00Z', '2026-11-19', '2026-11-12'],
            'over everywhere, once' => ['2026-11-12', false, '2026-11-13T12:00:00Z', null, '2026-11-12'],
            'a week on, the day itself' => ['2026-11-12', true, '2026-11-19T08:00:00Z', '2026-11-19', '2026-11-12'],
            'weeks later' => ['2026-11-12', true, '2026-12-01T15:00:00Z', '2026-12-03', '2026-11-26'],
            'across a new year' => ['2026-12-30', true, '2027-01-02T00:00:00Z', '2027-01-06', '2026-12-30'],
        ];
    }

    #[DataProvider('cases')]
    public function test_next_and_previous(string $date, bool $weekly, string $now, ?string $next, ?string $previous): void
    {
        $this->assertSame(
            ['date' => $date, 'weekly' => $weekly, 'next' => $next, 'previous' => $previous],
            RehearsalDates::of(CarbonImmutable::parse($date, 'UTC'), $weekly, CarbonImmutable::parse($now)),
        );
    }

    public function test_none_set(): void
    {
        $this->assertNull(RehearsalDates::of(null, true, CarbonImmutable::now()));
    }
}
