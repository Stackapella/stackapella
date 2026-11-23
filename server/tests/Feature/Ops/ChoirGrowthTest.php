<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Growth\ChoirGrowth;
use App\Actions\Growth\GrowthExport;
use App\Actions\OwnChoir\ChoirJobs;
use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Models\User;
use App\Support\OwnChoir\ChoirCounts;
use App\Support\Stats\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** AI choirs on Admin > Growth (admin.growth.choirs): songs made by plan, together, part-minutes, failures, capture to choir. */
final class ChoirGrowthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Midday, so "today" and "three days ago" never cross a midnight mid-test.
        $this->travelTo(Carbon::parse('2026-11-23 12:00:00', 'UTC'));
        config(['counts.enabled' => true]);
    }

    private function counted(string $day, string $kind, int $n): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => 'account', 'country' => '', 'variant' => '', 'source' => '', 'count' => $n]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return DB::table('usage_counts')->selectRaw('kind, sum(count) as n')->groupBy('kind')->pluck('n', 'kind')->map(static fn (mixed $n): int => (int) $n)->all();
    }

    public function test_growth_shows_each_days_choirs_by_plan_beside_the_period_before(): void
    {
        $today = ReportDay::now()->toDateString();
        $earlier = ReportDay::now()->subDays(3)->toDateString();
        $before = ReportDay::now()->subDays(40)->toDateString();
        $this->counted($today, 'choir_made_free', 2);
        $this->counted($today, 'choir_made_plus', 1);
        $this->counted($earlier, 'choir_made_comped', 1);
        $this->counted($today, 'own_choir_friend_ready', 3);
        $this->counted($today, 'choir_part_seconds', 1500);
        $this->counted($today, 'choir_failed_ours', 1);
        $this->counted($earlier, 'choir_failed_take', 2);
        $this->counted($today, 'choir_take_started', 8);
        $this->counted($before, 'choir_made_free', 4);
        $this->counted($before, 'choir_take_started', 5);
        $this->counted($today, 'not_choir', 99);

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/growth/choirs')->assertForbidden();

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/growth/choirs');
        $this->assertMatchesContract($shown, 'admin.growth.choirs', 200);
        $shown->assertJsonPath('data.period', '30d')->assertJsonPath('data.to', $today)
            ->assertJsonPath('data.totals.made', ['n' => 4, 'before' => 4])
            ->assertJsonPath('data.totals.free', ['n' => 2, 'before' => 4])
            ->assertJsonPath('data.totals.plus.n', 1)->assertJsonPath('data.totals.comped.n', 1)
            ->assertJsonPath('data.totals.together.n', 3)->assertJsonPath('data.totals.partMinutes.n', 25)
            ->assertJsonPath('data.totals.failedOurs.n', 1)->assertJsonPath('data.totals.failedTake.n', 2)
            ->assertJsonPath('data.funnel.captureStarted', ['n' => 8, 'before' => 5])
            ->assertJsonPath('data.funnel.made', ['n' => 4, 'before' => 4])
            ->assertJsonPath('data.funnel.rate', 0.5)->assertJsonPath('data.funnel.rateBefore', 0.8);

        // Every day of the period, zeros and all.
        $days = collect($shown->json('data.days'))->keyBy('date');
        $this->assertCount(30, $days);
        $this->assertSame([3, 2, 1, 0, 3, 25, 1, 0], array_values(array_slice($days[$today], 1, 8)));
        $this->assertSame([1, 0, 0, 1, 2], [$days[$earlier]['made'], $days[$earlier]['free'], $days[$earlier]['plus'], $days[$earlier]['comped'], $days[$earlier]['failedTake']]);

        $week = $this->getJson('/api/admin/growth/choirs?period=7d');
        $this->assertMatchesContract($week, 'admin.growth.choirs', 200);
        $week->assertJsonPath('data.period', '7d')->assertJsonPath('data.totals.made.before', 0)->assertJsonPath('data.funnel.rateBefore', null);
        $this->assertCount(7, $week->json('data.days'));
        $this->getJson('/api/admin/growth/choirs?period=year')->assertUnprocessable();

        // The growth pull carries the same, over 30 days.
        $this->assertSame(4, app(GrowthExport::class)->build('30')['choirs']['totals']['made']['n'] ?? null);
    }

    public function test_every_kind_it_reads_is_one_the_server_or_the_app_may_count(): void
    {
        $counted = [...(array) config('counts.server_kinds'), ...(array) config('counts.kinds')];

        foreach (ChoirGrowth::kinds() as $kind) {
            $this->assertContains($kind, $counted, "{$kind} is never counted.");
        }
    }

    public function test_a_song_made_counts_by_plan_and_a_friends_parts_or_a_remake_never_as_a_song(): void
    {
        $free = OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Ready]);
        $paid = OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Ready, 'priority' => 1]);
        $comped = OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Ready, 'user_id' => User::factory()->create(['plan' => 'plus'])->id]);
        $friend = OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Ready, 'parent_request_id' => $free->id]);
        $remake = OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Ready, 'parent_request_id' => $free->id, 'remake' => true]);

        $this->assertSame(['free', 'plus', 'comped'], [ChoirCounts::plan($free), ChoirCounts::plan($paid), ChoirCounts::plan($comped)]);

        foreach ([$free, $paid, $comped, $friend, $remake] as $song) {
            ChoirCounts::made($song);
        }

        $this->assertSame(['choir_made_comped' => 1, 'choir_made_free' => 1, 'choir_made_plus' => 1], $this->counts());
    }

    public function test_a_failure_is_the_takes_or_ours_once_and_an_abandoned_song_is_neither(): void
    {
        $song = fn (): OwnChoirRequest => OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Queued]);
        $take = $song();
        ChoirJobs::failAndClean($take, 'clip_unusable');
        // Failing a song already failed changes nothing and counts nothing.
        ChoirJobs::failAndClean($take, 'clip_unusable');
        ChoirJobs::failAndClean($song(), 'bad_clip');
        ChoirJobs::failAndClean($song(), 'render_failed');
        ChoirJobs::failAndClean($song(), 'expired');
        ChoirJobs::failAndClean($song(), 'reference_gone');

        $this->assertSame(['choir_failed_ours' => 2, 'choir_failed_take' => 1], $this->counts());
    }

    public function test_the_fill_counts_the_songs_on_the_server_once_by_day_and_never_over_live_counts(): void
    {
        $user = User::factory()->create();
        $yesterday = now()->subDay();
        $own = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'status' => OwnChoirStatus::Ready, 'ready_at' => $yesterday, 'first_claimed_at' => $yesterday, 'part_minutes' => 6.0, 'priority' => 1]);
        OwnChoirRequest::factory()->create(['user_id' => $user->id, 'status' => OwnChoirStatus::Deleted, 'ready_at' => now()->subDays(2), 'first_claimed_at' => now()->subDays(2), 'part_minutes' => 2.5]);
        $friend = OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Ready, 'ready_at' => $yesterday, 'first_claimed_at' => $yesterday, 'part_minutes' => 1.0, 'parent_request_id' => $own->id]);
        OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Failed, 'failure' => 'gates_failed', 'first_claimed_at' => $yesterday, 'part_minutes' => 3.0, 'updated_at' => $yesterday]);
        OwnChoirRequest::factory()->create(['status' => OwnChoirStatus::Failed, 'failure' => 'expired', 'updated_at' => $yesterday]);
        $this->counted($yesterday->toDateString(), 'own_choir_friend_ready', 1);
        $this->assertNotNull($friend->id);

        $fill = require database_path('migrations/2026_11_24_100000_fill_choir_growth_counts.php');
        $fill->up();
        $fill->up();

        $day = static fn (Carbon $at): string => $at->copy()->setTimezone(ReportDay::zone())->toDateString();
        $rows = DB::table('usage_counts')->whereIn('kind', ChoirCounts::GROWTH)->get()->map(static fn (object $r): string => substr((string) $r->day, 0, 10).' '.$r->kind.' '.$r->count)->sort()->values()->all();
        $this->assertSame([
            $day(now()->subDays(2)).' choir_made_free 1',
            $day(now()->subDays(2)).' choir_part_seconds 150',
            $day($yesterday).' choir_failed_ours 1',
            $day($yesterday).' choir_made_plus 1',
            $day($yesterday).' choir_part_seconds 600',
        ], $rows);
    }
}
