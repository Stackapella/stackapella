<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\CoachingPayment;
use App\Models\Engagement;
use App\Models\Take;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Stats\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin's days in the report timezone (config admin.report_timezone): around midnight, a count is
 * filed under the zone's day, and today, yesterday, the snapshot's days and revenue's months follow
 * it, while timestamps stay UTC. Tokyo here (UTC+9, no daylight saving).
 */
final class ReportTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['counts.enabled' => true, 'admin.report_timezone' => 'Asia/Tokyo']);
    }

    public function test_a_count_is_filed_under_the_zones_day_and_growth_reads_it_as_today(): void
    {
        // 20:00 UTC on the 31st is 05:00 on the 1st in Tokyo.
        $this->travelTo(Carbon::parse('2026-10-31 20:00:00', 'UTC'));
        $this->postJson('/api/counts', ['events' => [['kind' => 'f_arrive'], ['kind' => 'app_open']], 'signedIn' => false])->assertNoContent();
        $this->assertSame(['2026-11-01'], DB::table('usage_counts')->distinct()->pluck('day')->map(fn (mixed $d): string => substr((string) $d, 0, 10))->all());

        $this->actingAsAdmin();
        $growth = $this->getJson('/api/admin/growth');
        $this->assertMatchesContract($growth, 'admin.growth.show', 200);
        $growth->assertJsonPath('data.timezone', 'Asia/Tokyo')->assertJsonPath('data.date', '2026-11-01')->assertJsonPath('data.tiles.0.today', 1);
        $reach = $this->getJson('/api/admin/reach');
        $this->assertMatchesContract($reach, 'admin.reach.show', 200);
        $reach->assertJsonPath('data.today', ['day' => '2026-11-01', 'opens' => ['guest' => 1, 'account' => 0], 'devices' => null])
            ->assertJsonPath('data.days.'.(count((array) $reach->json('data.days')) - 1).'.day', '2026-10-31');
        $this->getJson('/api/admin/overview')->assertJsonPath('data.timezone', 'Asia/Tokyo');

        // Unset (or not a timezone): UTC, as before.
        config(['admin.report_timezone' => 'Not/AZone']);
        $this->assertSame('UTC', ReportDay::zone());
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']], 'signedIn' => false])->assertNoContent();
        $this->assertSame(1, DB::table('usage_counts')->where('day', '2026-10-31')->count());
    }

    public function test_the_morning_digest_is_for_yesterday_in_the_zone_and_goes_at_its_seven(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        // 21:30 UTC on the 10th is 06:30 on the 11th in Tokyo: not seven yet there, so nothing goes.
        $this->travelTo(Carbon::parse('2026-11-10 21:30:00', 'UTC'));
        $this->artisan('app:digest')->assertSuccessful();
        Notification::assertNothingSent();

        // 23:30 UTC is 08:30 on the 11th there: the digest goes, for the 10th (UTC would say the 9th).
        $this->travelTo(Carbon::parse('2026-11-10 23:30:00', 'UTC'));
        $this->artisan('app:digest')->assertSuccessful();
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => $alert->subject === __('ops.digest.subject', ['date' => '10 Nov']));
    }

    public function test_the_active_singers_days_and_revenues_months_follow_the_zone(): void
    {
        // 09:00 on the 1st in Tokyo: yesterday there is the 31st.
        $this->travelTo(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
        // 16:00 UTC on the 30th is 01:00 on the 31st in Tokyo.
        Take::factory()->create(['recorded_by' => User::factory()->create()->id, 'created_at' => Carbon::parse('2026-10-30 16:00:00', 'UTC')]);
        $this->artisan('app:counts:active-singers')->assertSuccessful();
        $this->assertSame(1, DB::table('active_singer_days')->where('day', '2026-10-31')->value('week'));
        $this->assertSame(0, DB::table('active_singer_days')->where('day', '2026-10-30')->value('week'));

        // 20:00 UTC on 30 September is 1 October in Tokyo: October's income.
        CoachingPayment::factory()->create(['engagement_id' => Engagement::factory()->create()->id, 'cents' => 2500, 'fee_cents' => 375, 'currency' => 'usd', 'paid_at' => Carbon::parse('2026-09-30 20:00:00', 'UTC')]);
        $this->actingAsAdmin();
        $revenue = $this->getJson('/api/admin/revenue');
        $this->assertMatchesContract($revenue, 'admin.revenue.show', 200);
        $revenue->assertJsonPath('data.timezone', 'Asia/Tokyo')->assertJsonPath('data.months.11.month', '2026-11')
            ->assertJsonPath('data.oneOff', [['month' => '2026-10', 'kind' => 'coaching', 'currency' => 'usd', 'payments' => 1, 'gross' => 2500, 'ours' => 375]]);
        $this->getJson('/api/admin/active')->assertJsonPath('data.weeks.11', ['to' => '2026-10-31', 'week' => 1, 'month' => 1])->assertJsonPath('data.timezone', 'Asia/Tokyo');
    }
}
