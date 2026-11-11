<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Breadth (docs/retention-plan.md, "Breadth"): the active device-days at each level, guests and accounts apart, the
 * share at three or more beside last week and four weeks ago, and each feature's first uses as a ladder beside the
 * new devices, all from br:<n> and ft:<feature> alone; an account's tried set is never read.
 */
final class BreadthTest extends TestCase
{
    use RefreshDatabase;

    private static function counted(string $day, string $kind, int $n, string $audience = 'guest'): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => $audience, 'country' => '', 'variant' => '', 'source' => '', 'count' => $n]);
    }

    public function test_the_levels_the_headline_and_the_ladder_read_from_the_counts_alone(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        // This week (11-06 to 11-12): 60 device-days at one, 30 at two, 10 at four (guests), and 20 at three (accounts).
        self::counted('2026-11-10', 'br:1', 60);
        self::counted('2026-11-10', 'br:2', 30);
        self::counted('2026-11-11', 'br:4', 10);
        self::counted('2026-11-11', 'br:3', 20, 'account');
        // Last week: one in ten at three or more; four weeks ago, none.
        self::counted('2026-11-02', 'br:1', 90);
        self::counted('2026-11-02', 'br:5', 10, 'account');
        self::counted('2026-10-10', 'br:1', 40);
        // Older than the five weeks: never read.
        self::counted('2026-10-01', 'br:9', 500);
        // The ladder: 80 new devices, 50 finished a lesson, 20 of those warmed up.
        self::counted('2026-11-09', 'f_arrive', 70);
        self::counted('2026-11-09', 'f_arrive_start', 10);
        self::counted('2026-11-09', 'ft:lesson', 50);
        self::counted('2026-11-10', 'ft:warmup', 20);
        self::counted('2026-11-03', 'ft:lesson', 44);
        // An account's tried set is its own: never read here.
        User::factory()->create(['tried' => ['lesson' => '2026-11-01', 'stack' => '2026-11-02', 'whole' => '2026-11-03']]);

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/breadth')->assertOk();
        $this->assertMatchesContract($shown, 'admin.breadth.show', 200);
        $data = (array) $shown->json('data');

        $this->assertSame(['2026-11-06', '2026-11-12', 30], [$data['from'], $data['to'], $data['floor']]);
        $this->assertSame(5, count($data['weeks']));
        $this->assertSame(['2026-10-09', '2026-10-15'], [$data['weeks'][0]['from'], $data['weeks'][0]['to']]);
        $week = $data['weeks'][4];
        $this->assertSame([0, 60, 30, 20, 10, 0, 0, 0, 0, 0, 0, 0], $week['all']['levels']);
        $this->assertSame([0, 60, 30, 0, 10, 0, 0, 0, 0, 0, 0, 0], $week['guest']['levels']);
        $this->assertSame(20, $week['account']['deviceDays']);
        $this->assertTrue($week['account']['tooFew']);
        // 30 of 120 at three or more; the mean (60 + 60 + 60 + 40) / 120.
        $this->assertEquals(['deviceDays' => 120, 'threePlus' => 0.25, 'mean' => 1.83, 'tooFew' => false, 'lastWeek' => 0.1, 'fourWeeksAgo' => 0], $data['headline']);
        $this->assertSame(0, $data['weeks'][1]['all']['deviceDays']);
        $this->assertNull($data['weeks'][1]['all']['threePlus']);

        $this->assertSame(80, $data['newDevices']);
        $this->assertSame(array_values((array) config('counts.features')), array_column($data['features'], 'feature'));
        $this->assertEquals(['feature' => 'lesson', 'firstUses' => 50, 'lastWeek' => 44, 'perNew' => 0.625, 'fromPrevious' => null, 'tooFew' => false], $data['features'][0]);
        $this->assertEquals(['feature' => 'warmup', 'firstUses' => 20, 'lastWeek' => 0, 'perNew' => 0.25, 'fromPrevious' => 0.4, 'tooFew' => false], $data['features'][1]);
        // Read against 20, under the floor: too few to tell.
        $this->assertTrue($data['features'][2]['tooFew']);
        $this->assertSame(0, $data['features'][2]['firstUses']);
    }

    public function test_only_an_admin_reads_it(): void
    {
        $this->getJson('/api/admin/breadth')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/breadth')->assertForbidden();
    }
}
