<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use App\Support\Flags;
use App\Support\Stats\GrowthStart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Growth's fresh start (Hillel, 2026-11-15: "i dont trust any of the growth data since we recently fixed for spam",
 * then "let's make it a switch i can set once and forget about"): on, every growth view and the export count only
 * from the start of the day (Jerusalem time) it was turned on; off, all history again. Nothing is deleted.
 */
final class GrowthFreshStartTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a3f1c9e07b2d4e6f8a1b3c5d7e9f0a2b4c6d8e0f';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.growth_data.token' => self::TOKEN, 'counts.enabled' => true]);
    }

    private static function day(): ?string
    {
        return (new GrowthStart(new Flags))->day();
    }

    private function counted(string $day, string $kind, int $n, string $source = ''): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n]);
    }

    public function test_turning_it_on_keeps_its_day_until_it_is_turned_off(): void
    {
        $this->assertNull(self::day());

        // 00:30 in Jerusalem on the 15th is still the 14th in UTC: Jerusalem's day counts.
        $this->travelTo(Carbon::parse('2026-11-14 22:30:00', 'UTC'));
        app(Flags::class)->set(GrowthStart::FLAG, 'on', null, 'fresh start');
        $this->assertSame('2026-11-15', self::day());

        // Saved on again later (a new reason): set once and forget, so the day stays put.
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00', 'UTC'));
        app(Flags::class)->set(GrowthStart::FLAG, 'on', null, 'still fresh');
        $this->assertSame('2026-11-15', self::day());

        // Off shows all history; on again starts from that day.
        app(Flags::class)->set(GrowthStart::FLAG, 'off', null, 'all history');
        $this->assertNull(self::day());
        app(Flags::class)->set(GrowthStart::FLAG, 'on', null, 'again');
        $this->assertSame('2026-11-18', self::day());
    }

    public function test_a_day_before_the_fresh_start_never_reaches_a_default_view_or_the_export(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'UTC'));
        // The spam days before, and the true day.
        foreach (['2026-11-10', '2026-11-14'] as $old) {
            $this->counted($old, 'sv_songs', 1000, 'reddit');
            $this->counted($old, 'f_arrive', 500, 'reddit');
        }
        $this->counted('2026-11-15', 'sv_songs', 7, 'reddit');
        $this->counted('2026-11-15', 'f_arrive', 5, 'reddit');
        User::factory()->create(['created_at' => Carbon::parse('2026-11-10 10:00:00', 'UTC')]);
        User::factory()->create(['created_at' => Carbon::parse('2026-11-15 10:00:00', 'UTC')]);
        app(Flags::class)->set(GrowthStart::FLAG, 'on', null, 'fresh start');

        // The growth export: every range starts at the fresh start.
        $export = $this->getJson('/api/internal/growth?range=30', ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();
        $export->assertJsonPath('meta.countFrom', '2026-11-15')->assertJsonPath('meta.range.from', '2026-11-15')->assertJsonPath('meta.range.days', 1)
            ->assertJsonPath('daily.from', '2026-11-15')->assertJsonPath('daily.dates', ['2026-11-15'])
            ->assertJsonPath('daily.kinds.sv_songs.guest', [7])->assertJsonPath('signups.total', 1);
        $this->assertSame(['2026-11-15'], $export->json('signups.days.dates'));

        // Admin: the Overview, Sources and Usage.
        $this->actingAsAdmin();
        $overview = $this->getJson('/api/admin/growth?period=28d')->assertOk();
        $overview->assertJsonPath('data.countFrom', '2026-11-15')->assertJsonPath('data.countingSince', '2026-11-15');
        $this->assertMatchesContract($overview, 'admin.growth.show', 200);
        $visits = collect((array) $overview->json('data.metrics'))->firstWhere('key', 'visits');
        $this->assertSame(7, $visits['now'] ?? null);

        $sources = $this->getJson('/api/admin/sources?days=30')->assertOk()->assertJsonPath('data.from', '2026-11-15');
        $this->assertSame(5, collect((array) $sources->json('data.sources'))->sum('arrivals'));
        $usage = $this->getJson('/api/admin/counts?days=30')->assertOk();
        $this->assertSame(['2026-11-15'], array_values(array_unique(array_column((array) $usage->json('data.days'), 'date'))));

        // Off: all history again, nothing deleted.
        app(Flags::class)->set(GrowthStart::FLAG, 'off', null, 'all history');
        app()->forgetScopedInstances();
        $this->assertSame(1005, collect((array) $this->getJson('/api/admin/sources?days=30')->assertOk()->json('data.sources'))->sum('arrivals'));
        $this->getJson('/api/admin/growth?period=28d')->assertJsonPath('data.countFrom', null);
        $this->assertSame(6, (int) DB::table('usage_counts')->count());
    }
}
