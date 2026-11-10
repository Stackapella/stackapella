<?php

declare(strict_types=1);

namespace Tests\Unit\Stats;

use App\Support\Stats\FairCompare;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * docs/growth-compare-plan.md: every period cut at the same wall-clock time as what it's compared with,
 * and a change said only when it's bigger than chance. The plan's worked examples are literal here.
 */
final class FairCompareTest extends TestCase
{
    private const LONDON = 'Europe/London';

    private static function at(string $local, string $zone = self::LONDON): CarbonImmutable
    {
        return CarbonImmutable::parse($local, $zone);
    }

    /**
     * @param  array<string, mixed>  $window
     * @return array{0: list<string>|null, 1: string|null}
     */
    private static function span(array $window): array
    {
        return [$window['full'], $window['partial']];
    }

    public function test_each_periods_windows_on_monday_9_november_at_10_23_in_london(): void
    {
        $now = self::at('2026-11-09 10:23');

        $today = FairCompare::windows('today', $now, self::LONDON);
        $this->assertSame(['key' => 'today', 'from' => '2026-11-09', 'to' => '2026-11-09', 'cut' => '10:23'], $today['period']);
        $this->assertSame([null, '2026-11-09'], self::span($today['current']));
        $this->assertSame([null, '2026-11-08'], self::span($today['previous']));
        // A usual Monday: Nov 2, Oct 26, Oct 19 and Oct 12, each to 10:23.
        $this->assertSame(['2026-11-02', '2026-10-26', '2026-10-19', '2026-10-12'], array_column($today['usual'], 'partial'));

        $week = FairCompare::windows('7d', $now, self::LONDON);
        $this->assertSame(['2026-11-03', '2026-11-09'], [$week['period']['from'], $week['period']['to']]);
        $this->assertSame([['2026-11-03', '2026-11-08'], '2026-11-09'], self::span($week['current']));
        $this->assertSame([['2026-10-27', '2026-11-01'], '2026-11-02'], self::span($week['previous']));
        $this->assertSame([[['2026-10-27', '2026-11-01'], '2026-11-02'], [['2026-10-20', '2026-10-25'], '2026-10-26'], [['2026-10-13', '2026-10-18'], '2026-10-19'], [['2026-10-06', '2026-10-11'], '2026-10-12']], array_map(self::span(...), $week['usual']));

        $four = FairCompare::windows('28d', $now, self::LONDON);
        $this->assertSame([['2026-10-13', '2026-11-08'], '2026-11-09'], self::span($four['current']));
        $this->assertSame([['2026-09-15', '2026-10-11'], '2026-10-12'], self::span($four['previous']));
        $this->assertSame([], $four['usual']);

        $twelve = FairCompare::windows('12w', $now, self::LONDON);
        $this->assertSame('2026-08-18', $twelve['period']['from']);
        $this->assertSame([['2026-05-26', '2026-08-16'], '2026-08-17'], self::span($twelve['previous']));

        $month = FairCompare::windows('month', $now, self::LONDON);
        $this->assertSame([['2026-11-01', '2026-11-08'], '2026-11-09'], self::span($month['current']));
        $this->assertSame([['2026-10-01', '2026-10-08'], '2026-10-09'], self::span($month['previous']));
        $this->assertSame(['2026-10-01', '2026-10-09'], [$month['previous']['from'], $month['previous']['to']]);
    }

    public function test_the_first_of_the_month_and_the_month_edge(): void
    {
        $first = FairCompare::windows('month', self::at('2026-11-01 09:00'), self::LONDON);
        $this->assertSame([null, '2026-11-01'], self::span($first['current']));
        $this->assertSame([null, '2026-10-01'], self::span($first['previous']));

        // March 30 against February: all of February, scaled to 29.4 days.
        $edge = FairCompare::windows('month', self::at('2027-03-30 10:00'), self::LONDON, 0.4);
        $this->assertSame([['2027-02-01', '2027-02-28'], null], self::span($edge['previous']));
        $this->assertEqualsWithDelta(29.4 / 28, $edge['previous']['scale'], 1e-9);
        $this->assertSame(29.4, $edge['previous']['scaledDays']);
    }

    public function test_the_cut_is_wall_clock_through_both_clock_changes_and_in_other_zones(): void
    {
        // 25 October 2026: 01:00 to 02:00 happens twice in London; a cut inside it is the first pass.
        $this->assertSame('2026-10-25 00:30', FairCompare::cutMoment('2026-10-25', '01:30', self::LONDON)->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-25 10:23', FairCompare::cutMoment('2026-10-25', '10:23', self::LONDON)->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-24 09:23', FairCompare::cutMoment('2026-10-24', '10:23', self::LONDON)->utc()->format('Y-m-d H:i'));
        // 28 March 2027: 01:00 to 02:00 doesn't happen; 01:30 is the gap's end, 02:00 BST.
        $this->assertSame('2027-03-28 01:00', FairCompare::cutMoment('2027-03-28', '01:30', self::LONDON)->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 15:23', FairCompare::cutMoment('2026-11-09', '10:23', 'America/New_York')->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 04:53', FairCompare::cutMoment('2026-11-09', '10:23', 'Asia/Kolkata')->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 10:23', FairCompare::cutMoment('2026-11-09', '10:23', 'UTC')->utc()->format('Y-m-d H:i'));

        // The 7 days around the autumn change: still dates, one of them 25 hours long, not adjusted.
        $week = FairCompare::windows('7d', self::at('2026-10-27 10:23'), self::LONDON);
        $this->assertSame([['2026-10-21', '2026-10-26'], '2026-10-27'], self::span($week['current']));
        // The windows are the report zone's own days, whatever zone `now` arrives in.
        $this->assertSame('2026-11-09', FairCompare::windows('today', CarbonImmutable::parse('2026-11-09 04:53', 'UTC'), 'Asia/Kolkata')['current']['partial']);
        $this->assertSame('10:23', FairCompare::windows('today', CarbonImmutable::parse('2026-11-09 04:53', 'UTC'), 'Asia/Kolkata')['period']['cut']);
    }

    public function test_a_days_part_up_to_the_cut_is_exact_from_its_hours_or_estimated_from_its_total(): void
    {
        $cut = FairCompare::cutMoment('2026-11-08', '10:23', self::LONDON);
        $hours = ['2026-11-08 08:00:00' => 4, '2026-11-08 09:00:00' => 6, '2026-11-08 10:00:00' => 6, '2026-11-08 15:00:00' => 4];

        // 4 + 6 + 6 x 23/60 of the 20 counted, of a day total of 20: exact.
        $this->assertEqualsWithDelta(4 + 6 + 6 * 23 / 60, FairCompare::partial(20, $hours, $cut, true, 0.3)['value'], 1e-9);
        $this->assertFalse(FairCompare::partial(20, $hours, $cut, true, 0.3)['estimated']);
        // A backfilled day whose hours sum short: the shape from the hours, the total from the day.
        $this->assertEqualsWithDelta(40 * (4 + 6 + 6 * 23 / 60) / 20, FairCompare::partial(40, $hours, $cut, true, 0.3)['value'], 1e-9);
        // No hours for the day: the usual share of its total, marked estimated.
        $this->assertSame(['value' => 12.0, 'estimated' => true], FairCompare::partial(40, [], $cut, false, 0.3));
        $this->assertSame(['value' => 12.0, 'estimated' => true], FairCompare::partial(40, [], $cut, true, 0.3));
        // Nothing that day: exactly none.
        $this->assertSame(['value' => 0.0, 'estimated' => false], FairCompare::partial(0, [], $cut, false, 0.3));

        // The autumn day: a cut at 01:30 takes the first pass of 01:00 and half of it.
        $autumn = FairCompare::cutMoment('2026-10-25', '01:30', self::LONDON);
        $passes = ['2026-10-24 23:00:00' => 2, '2026-10-25 00:00:00' => 4, '2026-10-25 01:00:00' => 8, '2026-10-25 12:00:00' => 6];
        $this->assertEqualsWithDelta(2 + 4 / 2, FairCompare::partial(20, $passes, $autumn, true, 0.1)['value'], 1e-9);
    }

    public function test_a_usual_days_shape(): void
    {
        // A flat day: half done by noon, whatever the sample.
        $this->assertEqualsWithDelta(0.5, FairCompare::shape(array_fill(0, 24, 10), 4, 720), 1e-9);
        $this->assertSame(0.0, FairCompare::shape(array_fill(0, 24, 10), 4, 0));
        $this->assertSame(1.0, FairCompare::shape(array_fill(0, 24, 10), 4, 1440));
        // No data at all: a flat day.
        $this->assertEqualsWithDelta(10 / 24, FairCompare::shape(array_fill(0, 24, 0), 0, 600), 1e-9);

        // A day that comes in the evening: well under a flat day's share at 10:00, more so with more data.
        $evening = array_merge(array_fill(0, 18, 1), array_fill(0, 6, 60));
        $few = FairCompare::shape($evening, 4, 600);
        $many = FairCompare::shape(array_map(static fn (int $w): int => $w * 20, $evening), 4, 600);
        $this->assertLessThan(10 / 24, $few);
        $this->assertLessThan($few, $many);
    }

    public function test_the_plans_worked_examples(): void
    {
        // 1 vs 2: too few, never "-50%".
        $this->assertSame(['verdict' => 'tooFew', 'z' => null, 'change' => null], FairCompare::judge(1, 2.0, 1, false));
        // 15 now, yesterday by 10:00 about 12 (40 x 0.30): about the same, +3.
        $this->assertSame(['verdict' => 'same', 'z' => 0.68, 'change' => ['kind' => 'count', 'value' => 3]], FairCompare::judge(15, 40 * 0.30, 0.30, true));
        // 7-day arrivals 260 against a usual week of 210 (840 over 4): up, +24%, usually 178 to 242.
        $this->assertSame(['verdict' => 'up', 'z' => 3.09, 'change' => ['kind' => 'percent', 'value' => 24], 'low' => 178, 'high' => 242], FairCompare::judge(260, 840 / 4, 0.25, false, true));
        // 7-day sign-ups 9 against a usual 6: about the same, +3.
        $this->assertSame(['verdict' => 'same', 'z' => 1.1, 'change' => ['kind' => 'count', 'value' => 3]], FairCompare::judge(9, 24 / 4, 0.25, false));
    }

    public function test_the_other_verdicts(): void
    {
        $this->assertSame('none', FairCompare::judge(0, 0.0, 1, false)['verdict']);
        $this->assertSame('new', FairCompare::judge(6, 0.0, 1, false)['verdict']);
        // Today at 0 against a usual 30: down, and said.
        $this->assertSame('down', FairCompare::judge(0, 30.0, 0.25, false)['verdict']);
        $this->assertSame('maybeUp', FairCompare::judge(30, 20.0, 1, false)['verdict']);
        $this->assertSame('maybeDown', FairCompare::judge(78, 100.0, 1, false)['verdict']);
        // Above +200%: so many times as many, to the half.
        $this->assertSame(['kind' => 'times', 'value' => 3.5], FairCompare::judge(70, 20.0, 1, false)['change']);
        $this->assertSame(['kind' => 'percent', 'value' => -40], FairCompare::judge(60, 100.0, 1, false)['change']);
        // An estimate is a quarter less certain, so the same gap is a weaker verdict.
        $this->assertGreaterThan(FairCompare::judge(130, 100.0, 1, true)['z'], FairCompare::judge(130, 100.0, 1, false)['z']);
    }

    public function test_the_projections_worked_examples(): void
    {
        // Today, 10:23, 48 so far, a usual day 30% in: about 160 (108 to 212).
        $this->assertSame(['shown' => true, 'hidden' => null, 'value' => 160, 'low' => 108, 'high' => 212], FairCompare::projectDay(48, 0.30));
        // November 9 at 10:23, 300 so far, 8.3 of 30 days in: about 1,084 (944 to 1,224), 36.1 a day.
        $this->assertSame(['shown' => true, 'hidden' => null, 'value' => 1084, 'low' => 944, 'high' => 1224, 'rate' => 36.1], FairCompare::projectMonth(300, 8.30, 30));
        // 12 weeks, Thursday noon: Monday to Wednesday 0.15, 0.15, 0.14 and Thursday 0.15 at half a day in.
        $w = FairCompare::weekDone([0.15, 0.15, 0.14, 0.15, 0.14, 0.13, 0.14], 3, 0.5);
        $this->assertEqualsWithDelta(0.515, $w, 1e-9);
        $this->assertSame(['shown' => true, 'hidden' => null, 'value' => 200, 'low' => 156, 'high' => 244], FairCompare::projectWeek(103, $w));
    }

    public function test_each_projection_is_hidden_at_its_edge(): void
    {
        $this->assertSame('early', FairCompare::projectDay(48, 0.24)['hidden']);
        $this->assertTrue(FairCompare::projectDay(48, 0.25)['shown']);
        $this->assertSame('tooFew', FairCompare::projectDay(14, 0.5)['hidden']);
        $this->assertTrue(FairCompare::projectDay(15, 0.5)['shown']);
        $this->assertSame('early', FairCompare::projectMonth(300, 2.9, 30)['hidden']);
        $this->assertTrue(FairCompare::projectMonth(300, 3.0, 30)['shown']);
        $this->assertSame('tooFew', FairCompare::projectMonth(29, 10.0, 30)['hidden']);
        $this->assertSame('early', FairCompare::projectWeek(100, 0.24)['hidden']);
        $this->assertSame('noHistory', FairCompare::projectDay(100, 0.8, history: false)['hidden']);
        // Zero so far: too few, never a projection of nothing.
        $this->assertSame(['shown' => false, 'hidden' => 'tooFew', 'value' => null, 'low' => null, 'high' => null], FairCompare::projectDay(0, 0.5));

        // When a flat day reaches a quarter: 06:00; an evening day, later.
        $this->assertSame('06:00', FairCompare::reaches(array_fill(0, 24, 10), 4, 0.25));
        $this->assertGreaterThan('06:00', (string) FairCompare::reaches(array_map(static fn (int $h): int => $h >= 17 ? 400 : 1, range(0, 23)), 4, 0.25));
    }

    public function test_a_usual_weeks_shares(): void
    {
        // No counts: a flat week.
        $this->assertEqualsWithDelta(array_fill(0, 7, 1 / 7), FairCompare::weekShares(array_fill(0, 7, 0)), 1e-9);
        // A weekend-heavy sample, drawn toward flat by its size; the shares always sum to 1.
        $shares = FairCompare::weekShares([10, 10, 10, 10, 10, 100, 100]);
        $this->assertEqualsWithDelta(1.0, array_sum($shares), 1e-9);
        $this->assertGreaterThan($shares[0], $shares[5]);
        $this->assertEqualsWithDelta(3 / 7, FairCompare::weekDone(array_fill(0, 7, 1 / 7), 3, 0.0), 1e-9);
    }
}
