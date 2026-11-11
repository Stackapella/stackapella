<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\DropoffReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The weekly drop-off report (docs/retention-plan.md, "Detect"; the coordinator, 2026-11-11): a planted drop ranks
 * first, a step under the floor is never ranked, and Admin and the growth data read the latest week.
 */
final class DropoffsTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK_END = '2026-11-12';

    /**
     * @param  array<string, int>  $totals  kind => devices in the week
     */
    private static function counted(array $totals, string $day = '2026-11-10'): void
    {
        foreach ($totals as $kind => $n) {
            DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => '', 'count' => $n]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function report(): array
    {
        $this->artisan('app:dropoffs', ['--week-end' => self::WEEK_END])->assertSuccessful();

        return (array) DropoffReport::query()->where('week_end', self::WEEK_END)->firstOrFail()->data;
    }

    public function test_a_planted_drop_ranks_first_by_singers_lost(): void
    {
        // Lesson 1 (two steps): nearly everyone goes on, except from its first step to its second. (Lesson 2 has three.)
        self::counted(['fl_s:1-comfortable' => 200, 'fs_r:1-comfortable.0' => 190, 'fs_r:1-comfortable.1' => 60, 'fl_d:1-comfortable' => 57]);
        // A milder drop that fewer meet ranks after it.
        self::counted(['fl_s:1-match' => 60, 'fs_r:1-match.0' => 57, 'fs_r:1-match.1' => 35, 'fs_r:1-match.2' => 34, 'fl_d:1-match' => 33]);

        $report = $this->report();

        $this->assertSame(['2026-11-06', self::WEEK_END], [$report['weekStart'], $report['weekEnd']]);
        $first = $report['worst'][0];
        $this->assertSame(['lesson:1-comfortable', 'fs_r:1-comfortable.0', 190, 60, true], [$first['path'], $first['step'], $first['entered'], $first['passed'], $first['drop']]);
        $this->assertSame(0.95, $first['neighbours']);
        $this->assertSame((int) round(190 * (0.95 - 0.316)), $first['lost']);
        $match = collect(collect($report['paths'])->firstWhere('path', 'lesson:1-match')['steps'])->firstWhere('step', 'fs_r:1-match.0');
        $this->assertTrue($match['drop']);
        $this->assertSame((int) round($match['entered'] * ($match['neighbours'] - $match['passThrough'])), $match['lost']);
        $this->assertLessThan($first['lost'], $match['lost']);
    }

    public function test_a_step_too_few_reached_is_never_ranked(): void
    {
        config(['counts.dropoffs.floor' => 30]);
        // A steep fall, but only ten started: too few to tell.
        self::counted(['fl_s:1-comfortable' => 10, 'fs_r:1-comfortable.0' => 1, 'fs_r:1-comfortable.1' => 1, 'fl_d:1-comfortable' => 1]);

        $report = $this->report();

        $this->assertSame([], $report['worst']);

        // Over the long window (28 days) the same lesson can be read: earlier weeks add up past the floor.
        self::counted(['fl_s:1-comfortable' => 40, 'fs_r:1-comfortable.0' => 39], '2026-10-20');
        $longer = collect($this->report()['paths'])->firstWhere('path', 'lesson:1-comfortable')['steps'][0];
        $this->assertTrue($longer['tooFew']);
        // (MySQL's JSON column keeps its own key order.)
        $this->assertEquals(['entered' => 50, 'passed' => 40, 'passThrough' => 0.8, 'tooFew' => false], $longer['month']);
        $lesson = collect($report['paths'])->firstWhere('path', 'lesson:1-comfortable');
        $this->assertTrue($lesson['steps'][0]['tooFew']);
        $this->assertFalse($lesson['steps'][0]['drop']);
        // The funnel had nobody: every step too few, none a drop.
        $this->assertSame([true], array_values(array_unique(array_column(collect($report['paths'])->firstWhere('path', 'funnel')['steps'], 'tooFew'))));
    }

    public function test_a_step_that_fell_on_its_own_weeks_before_is_a_fall(): void
    {
        // The four weeks before: 90% went on from step 1 to step 2 (as many as the floor each week).
        self::counted(['fl_s:1-comfortable' => 400, 'fs_r:1-comfortable.0' => 400, 'fs_r:1-comfortable.1' => 360, 'fl_d:1-comfortable' => 350], '2026-10-20');
        // This week only half did: it fell on its own weeks before.
        self::counted(['fl_s:1-comfortable' => 100, 'fs_r:1-comfortable.0' => 100, 'fs_r:1-comfortable.1' => 50, 'fl_d:1-comfortable' => 49]);

        $report = $this->report();

        $this->assertSame('fs_r:1-comfortable.0', $report['falls'][0]['step']);
        $this->assertSame(0.4, $report['falls'][0]['fall']);
    }

    public function test_admin_and_the_growth_data_read_the_latest_week(): void
    {
        DropoffReport::factory()->create(['week_end' => '2026-11-05', 'data' => ['weekStart' => '2026-10-30', 'weekEnd' => '2026-11-05', 'floor' => 30, 'margin' => 0.15, 'paths' => [], 'worst' => [], 'falls' => []]]);
        self::counted(['fl_s:1-comfortable' => 200, 'fs_r:1-comfortable.0' => 190, 'fs_r:1-comfortable.1' => 60, 'fl_d:1-comfortable' => 57]);
        $this->report();

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/dropoffs');
        $this->assertMatchesContract($shown, 'admin.dropoffs.show', 200);
        $this->assertSame(self::WEEK_END, $this->getJson('/api/admin/dropoffs')->json('data.latest.weekEnd'));
        $shown->assertJsonPath('data.latest.weekEnd', self::WEEK_END)->assertJsonPath('data.previous.weekEnd', '2026-11-05')->assertJsonPath('data.latest.worst.0.step', 'fs_r:1-comfortable.0');
    }
}
