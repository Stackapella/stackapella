<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * admin.growth.show's fair comparisons (docs/growth-compare-plan.md, 5.5; Hillel, 2026-11-09): at 10:23
 * on Monday 9 November in London, today against yesterday by 10:23 (exact from its hours) and a usual
 * Monday by 10:23 (estimated from the day totals), every period cut at the same clock time.
 */
final class GrowthCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.report_timezone' => 'Europe/London']);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:23', 'Europe/London'));

        $day = static fn (string $date, string $kind, int $n): array => ['day' => $date, 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => '', 'count' => $n];
        DB::table('usage_counts')->insert([
            $day('2026-11-09', 'f_arrive', 30), $day('2026-11-08', 'f_arrive', 20),
            $day('2026-11-02', 'f_arrive', 20), $day('2026-10-26', 'f_arrive', 20), $day('2026-10-19', 'f_arrive', 20), $day('2026-10-12', 'f_arrive', 20),
            $day('2026-11-09', 'sv_courses', 4), $day('2026-11-08', 'sv_courses', 3),
            $day('2026-11-09', 'ask_acct_course_shown', 40), $day('2026-11-09', 'ask_acct_course_yes', 6),
        ]);
        // Hourly counting began the evening before (a visit at 23:00 on the 7th), so all of the 8th is covered.
        DB::table('usage_count_hours')->insert(['hour' => '2026-11-07 23:00:00', 'kind' => 'site_visit', 'audience' => 'guest', 'source' => '', 'count' => 1]);
        // Yesterday's arrivals by the hour (London is on UTC in November): 4, 6, 6 and 4.
        foreach (['08' => 4, '09' => 6, '10' => 6, '15' => 4] as $hour => $n) {
            DB::table('usage_count_hours')->insert(['hour' => "2026-11-08 {$hour}:00:00", 'kind' => 'f_arrive', 'audience' => 'guest', 'source' => '', 'count' => $n]);
        }
    }

    public function test_today_against_yesterday_by_this_time_and_a_usual_monday(): void
    {
        $this->actingAsAdmin();
        $today = $this->getJson('/api/admin/growth?period=today');
        $this->assertMatchesContract($today, 'admin.growth.show', 200);

        $today->assertJsonPath('data.period.key', 'today')->assertJsonPath('data.period.cut', '10:23')->assertJsonPath('data.timezone', 'Europe/London')
            ->assertJsonPath('data.comparisons.0', ['key' => 'previous', 'from' => '2026-11-08', 'to' => '2026-11-08', 'cut' => '10:23', 'scaledDays' => null])
            ->assertJsonPath('data.hourlySince', '2026-11-07T23:00:00Z')->assertJsonPath('data.countingSince', '2026-10-12');
        $this->assertSame(['visits', 'arrivals', 'lesson1', 'lessons', 'signUps', 'asks', 'day2', 'plus'], array_column((array) $today->json('data.metrics'), 'key'));

        $arrivals = collect((array) $today->json('data.metrics'))->firstWhere('key', 'arrivals');
        $this->assertSame(30, $arrivals['now']);
        // Yesterday by 10:23, exact from its hours: 4 + 6 + 6 x 23/60 of its 20.
        $this->assertSame(['expected' => 12.3, 'base' => 12, 'k' => 1, 'estimated' => false, 'verdict' => 'up', 'z' => 3.57, 'change' => ['kind' => 'count', 'value' => 18]], $arrivals['previous']);
        // A usual Monday by 10:23: four day totals of 20 at a flat day's share (623 of 1440 minutes, no shape yet).
        $usual = $arrivals['usual'];
        $this->assertSame([8.7, 80, true, 4, 'up'], [$usual['expected'], $usual['base'], $usual['estimated'], $usual['available'], $usual['verdict']]);
        $this->assertSame(['kind' => 'count', 'value' => 21], $usual['change']);
        $this->assertLessThan($usual['high'], $usual['low']);

        // Too few is never a percentage; nothing either time says so.
        $plus = collect((array) $today->json('data.metrics'))->firstWhere('key', 'plus');
        $this->assertSame('noHistory', $plus['previous']['verdict']);
        // The account asks: yes now, shown beside it.
        $asks = collect((array) $today->json('data.metrics'))->firstWhere('key', 'asks');
        $this->assertSame([6, 'shown', 40, 0.15], [$asks['now'], $asks['rate']['of'], $asks['rate']['base'], $asks['rate']['now']]);

        // The counting change on 8 November lies inside the comparison.
        $today->assertJsonPath('data.breaks.0', ['date' => '2026-11-08', 'kinds' => ['*'], 'note' => 'crawlers']);
        // Two changes on one day are two breaks.
        config(['counts.breaks' => ['2026-11-08' => [['kinds' => ['ft:*'], 'note' => 'a'], ['kinds' => '*', 'note' => 'b']]]]);
        $this->getJson('/api/admin/growth?period=today')->assertOk()
            ->assertJsonPath('data.breaks.0', ['date' => '2026-11-08', 'kinds' => ['ft:*'], 'note' => 'a'])
            ->assertJsonPath('data.breaks.1', ['date' => '2026-11-08', 'kinds' => ['*'], 'note' => 'b']);
        $today->assertJsonPath('data.period.early', false);

        // Aggregates only: no ids, no sources, no countries.
        $json = (string) $today->getContent();
        foreach (['"id"', '"source"', '"country"', '"user'] as $never) {
            $this->assertStringNotContainsString($never, $json);
        }
    }

    public function test_each_period_and_the_old_days_choices(): void
    {
        $this->actingAsAdmin();

        $week = $this->getJson('/api/admin/growth?period=7d');
        $this->assertMatchesContract($week, 'admin.growth.show', 200);
        $week->assertJsonPath('data.period.from', '2026-11-03')->assertJsonPath('data.comparisons.0.from', '2026-10-27')->assertJsonPath('data.comparisons.0.to', '2026-11-02');
        $arrivals = collect((array) $week->json('data.metrics'))->firstWhere('key', 'arrivals');
        $this->assertSame(50, $arrivals['now']);
        // The 7 before: Oct 27 to Nov 1 whole (none) and Nov 2 to 10:23, estimated.
        $this->assertSame([8.7, true, 1], [$arrivals['previous']['expected'], $arrivals['previous']['estimated'], $arrivals['previous']['k']]);
        // A usual week: only 3 of the 4 windows begin after counting began (Oct 12).
        $this->assertSame(3, $arrivals['usual']['available']);

        // The site sections, each the last 7 days against the 7 before; tracking is always the 7 days.
        $courses = collect((array) $week->json('data.sections'))->firstWhere('key', 'sv_courses');
        $this->assertSame(7, $courses['now']);
        $this->assertSame('noHistory', $courses['previous']['verdict']);
        $this->assertSame(['visits', 'arrivals', 'firstLessons', 'signUps'], array_column((array) $week->json('data.tracking.metrics'), 'key'));
        $week->assertJsonPath('data.tracking.metrics.2.measure', 'lesson1')->assertJsonPath('data.tracking.period.key', '7d');

        // Month to date against last month to the same day and time.
        $month = $this->getJson('/api/admin/growth?period=month');
        $this->assertMatchesContract($month, 'admin.growth.show', 200);
        $month->assertJsonPath('data.comparisons.0.from', '2026-10-01')->assertJsonPath('data.comparisons.0.to', '2026-10-09')->assertJsonPath('data.metrics.1.usual', null);

        // The old ?days=: 30 is the 4 weeks now, 84 the 12 weeks.
        $this->getJson('/api/admin/growth?days=30')->assertJsonPath('data.period.key', '28d')->assertJsonPath('data.from', '2026-10-13');
        $this->getJson('/api/admin/growth?days=84')->assertJsonPath('data.period.key', '12w');
        $this->getJson('/api/admin/growth?period=nonsense')->assertJsonPath('data.period.key', 'today');
    }

    public function test_each_periods_chart_and_its_projection(): void
    {
        $this->actingAsAdmin();

        // Today by the hour: yesterday's arrivals exact from its hours, a usual Monday's band from four days, and
        // today's total projected from its 30 so far over a usual day's share by 10:23 (a flat day: 623 of 1440).
        $today = $this->getJson('/api/admin/growth?period=today');
        $this->assertMatchesContract($today, 'admin.growth.show', 200);
        $chart = $today->json('data.chart');
        $this->assertSame(['hours', 24, ['slot' => 10, 'fraction' => 0.38]], [$chart['kind'], count($chart['slots']), $chart['now']]);
        $arrivals = $chart['metrics']['arrivals'];
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0, 4, 6, 6, 0, 0, 0, 0, 4, 0, 0, 0, 0, 0, 0, 0, 0], array_map('intval', $arrivals['previous']));
        $this->assertSame([20, false, 4], [$arrivals['previousTotal'], $arrivals['previousEstimated'][9], $arrivals['usual']['available']]);
        $this->assertNull($arrivals['now'][11]);
        $projection = $arrivals['projection'];
        $this->assertSame([true, 'dayShape', 69, 10], [$projection['shown'], $projection['basis'], $projection['value'], $projection['slot']['index']]);
        $this->assertCount(14, $projection['path']['mean']);
        $this->assertLessThan($projection['value'], $projection['low']);
        // Visits began on the 8th: no shape for them yet, so no projection.
        $this->assertSame([false, 'noHistory'], [$chart['metrics']['visits']['projection']['shown'], $chart['metrics']['visits']['projection']['hidden']]);
        // Sign-ups as a strip of six 4-hour cells, the ones still to come empty.
        $this->assertCount(6, $chart['metrics']['signUps']['now']);
        $this->assertNull($chart['metrics']['signUps']['now'][3]);

        // 7 days by day: today's column projected, the week's projected total its days plus that.
        $week = $this->getJson('/api/admin/growth?period=7d')->json('data.chart');
        $this->assertSame(['days', 7, 6], [$week['kind'], count($week['slots']), $week['now']['slot']]);
        $this->assertEquals([0, 0, 0, 0, 0, 20, 30], $week['metrics']['arrivals']['now']);
        $this->assertEquals(20, $week['metrics']['arrivals']['previous'][6]);
        $this->assertSame([69, 89], [$week['metrics']['arrivals']['projection']['slot']['value'], $week['metrics']['arrivals']['projection']['periodTotal']]);
        $this->assertCount(28, $this->getJson('/api/admin/growth?period=28d')->json('data.chart.slots'));

        // The month: every day of November, the days to come empty, October's same days as before (31 has none).
        $month = $this->getJson('/api/admin/growth?period=month');
        $this->assertMatchesContract($month, 'admin.growth.show', 200);
        $chart = $month->json('data.chart');
        $this->assertSame(['days', 30, '2026-11-30'], [$chart['kind'], count($chart['slots']), $chart['slots'][29]]);
        $this->assertNull($chart['metrics']['arrivals']['now'][9]);
        $this->assertSame([true, 'dailyRate'], [$chart['metrics']['arrivals']['projection']['shown'], $chart['metrics']['arrivals']['projection']['basis']]);
        $this->assertSame($chart['metrics']['arrivals']['projection']['value'], $chart['metrics']['arrivals']['projection']['periodTotal']);

        // 12 weeks by Monday-to-Sunday week; on a Monday morning this week's projection waits.
        $weeks = $this->getJson('/api/admin/growth?period=12w');
        $this->assertMatchesContract($weeks, 'admin.growth.show', 200);
        $chart = $weeks->json('data.chart');
        $this->assertSame(['weeks', 12, '2026-11-09'], [$chart['kind'], count($chart['slots']), $chart['slots'][11]]);
        $this->assertSame('early', $chart['metrics']['arrivals']['projection']['hidden']);
        $this->assertMatchesRegularExpression('/^(monday|tuesday|wednesday)-(morning|afternoon|evening)$/', (string) $chart['metrics']['arrivals']['projection']['showsFrom']);
    }

    public function test_app_starts_sit_under_visits_and_visits_borrow_the_apps_day_shape_until_they_have_their_own(): void
    {
        // The app's hours go back three weeks; Visits' only three days (the section counts came later).
        foreach (range(1, 21) as $back) {
            $date = CarbonImmutable::parse('2026-11-09')->subDays($back)->toDateString();
            DB::table('usage_count_hours')->insert(['hour' => "{$date} 06:00:00", 'kind' => 'f_arrive', 'audience' => 'guest', 'source' => '', 'count' => 3]);
        }
        foreach (range(1, 3) as $back) {
            DB::table('usage_count_hours')->insert(['hour' => CarbonImmutable::parse('2026-11-09')->subDays($back)->toDateString().' 09:00:00', 'kind' => 'sv_songs', 'audience' => 'guest', 'source' => '', 'count' => 2]);
        }
        // App starts this morning, by the hour.
        DB::table('usage_count_hours')->insert(['hour' => '2026-11-09 08:00:00', 'kind' => 'app_open', 'audience' => 'guest', 'source' => '', 'count' => 5]);
        DB::table('usage_counts')->insert(['day' => '2026-11-09', 'kind' => 'app_open', 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => '', 'count' => 5]);

        $this->actingAsAdmin();
        $today = $this->getJson('/api/admin/growth?period=today');
        $this->assertMatchesContract($today, 'admin.growth.show', 200);
        $metrics = $today->json('data.chart.metrics');
        $this->assertSame(['borrowedShape', 'arrivals'], [$metrics['visits']['projection']['basis'], $metrics['visits']['projection']['shapeFrom']]);
        $this->assertSame('dayShape', $metrics['arrivals']['projection']['basis']);
        $this->assertArrayNotHasKey('shapeFrom', $metrics['arrivals']['projection']);
        $this->assertCount(count($metrics['signUps']['now']), $metrics['appStarts']['now'], 'a strip like Sign-ups');
        $this->assertEquals(5, array_sum(array_map(static fn (mixed $v): float => (float) $v, array_filter($metrics['appStarts']['now'], static fn (mixed $v): bool => $v !== null))));
    }
}
