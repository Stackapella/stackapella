<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * People using the app, signed in against guests (docs/admin-analytics-plan.md; Hillel, 2026-10-31),
 * from the anonymous daily counts: opens with their history, and devices and weekly reach only once
 * their kinds were counted, never read as 0 before.
 */
final class AdminReachTest extends TestCase
{
    use RefreshDatabase;

    private function counted(string $day, string $kind, string $audience, int $count, string $country = 'GB'): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => $audience, 'country' => $country, 'count' => $count]);
    }

    public function test_opens_devices_and_weekly_reach_split_signed_in_and_guests(): void
    {
        // A Saturday: yesterday is in the week of Monday 26 October; the one before is whole.
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));
        $this->counted('2026-10-19', 'app_open', 'guest', 14);
        $this->counted('2026-10-19', 'app_open', 'guest', 7, 'US');
        $this->counted('2026-10-19', 'app_open', 'account', 7);
        $this->counted('2026-10-25', 'app_open', 'guest', 7);
        $this->counted('2026-10-31', 'app_open', 'guest', 50); // today: not finished
        $this->counted('2026-05-10', 'app_open', 'guest', 50); // before the 24 weeks
        // app_day from a Wednesday (maybe partial), app_week from the Tuesday before.
        $this->counted('2026-10-14', 'app_day', 'guest', 3);
        $this->counted('2026-10-20', 'app_day', 'guest', 5);
        $this->counted('2026-10-20', 'app_day', 'account', 2);
        $this->counted('2026-10-30', 'app_day', 'account', 4);
        $this->counted('2026-10-13', 'app_week', 'guest', 2);
        $this->counted('2026-10-19', 'app_week', 'guest', 9);
        $this->counted('2026-10-19', 'app_week', 'account', 3);
        $this->counted('2026-10-22', 'app_week', 'guest', 1);

        $this->actingAsAdmin();
        $reach = $this->getJson('/api/admin/reach');
        $this->assertMatchesContract($reach, 'admin.reach.show', 200);
        $reach->assertJsonCount(12, 'data.weeks')->assertJsonCount(12, 'data.previous')
            // From the first week's Monday to yesterday.
            ->assertJsonCount(166, 'data.days')
            ->assertJsonPath('data.previous.0.from', '2026-05-18')
            ->assertJsonPath('data.days.0', ['day' => '2026-05-18', 'opens' => ['guest' => 0, 'account' => 0], 'devices' => null])
            // The week yesterday is in, so far: 5 finished days.
            ->assertJsonPath('data.weeks.11', ['from' => '2026-10-26', 'to' => '2026-11-01', 'days' => 5, 'opens' => ['guest' => 0, 'account' => 0], 'devices' => ['guest' => 0, 'account' => 0.8], 'reach' => ['guest' => 0, 'account' => 0]])
            ->assertJsonPath('data.weeks.10', ['from' => '2026-10-19', 'to' => '2026-10-25', 'days' => 7, 'opens' => ['guest' => 4, 'account' => 1], 'devices' => ['guest' => 0.7, 'account' => 0.3], 'reach' => ['guest' => 10, 'account' => 3]])
            // app_day's first day (maybe partial) is left out of its week; app_week's week isn't whole: null.
            ->assertJsonPath('data.weeks.9', ['from' => '2026-10-12', 'to' => '2026-10-18', 'days' => 7, 'opens' => ['guest' => 0, 'account' => 0], 'devices' => ['guest' => 0, 'account' => 0], 'reach' => null])
            ->assertJsonPath('data.days.148', ['day' => '2026-10-13', 'opens' => ['guest' => 0, 'account' => 0], 'devices' => null])
            ->assertJsonPath('data.days.149', ['day' => '2026-10-14', 'opens' => ['guest' => 0, 'account' => 0], 'devices' => ['guest' => 3, 'account' => 0]])
            ->assertJsonPath('data.days.154', ['day' => '2026-10-19', 'opens' => ['guest' => 21, 'account' => 7], 'devices' => ['guest' => 0, 'account' => 0]])
            ->assertJsonPath('data.days.165', ['day' => '2026-10-30', 'opens' => ['guest' => 0, 'account' => 0], 'devices' => ['guest' => 0, 'account' => 4]])
            // Today so far, apart from the finished days.
            ->assertJsonPath('data.today', ['day' => '2026-10-31', 'opens' => ['guest' => 50, 'account' => 0], 'devices' => ['guest' => 0, 'account' => 0]]);
    }

    public function test_counts_younger_than_a_whole_week_still_show_in_the_week_yesterday_is_in(): void
    {
        // Production on 2026-11-01 (a Sunday): app_open counted only since a deploy on the 28th,
        // so every week up to the last finished Sunday had nothing, and the chart was blank.
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00', 'UTC'));
        $this->counted('2026-10-28', 'app_open', 'guest', 5); // the deploy's day: partial
        $this->counted('2026-10-29', 'app_open', 'guest', 30);
        $this->counted('2026-10-29', 'app_open', 'guest', 12, 'US');
        $this->counted('2026-10-29', 'app_open', 'account', 6);
        $this->counted('2026-10-30', 'app_open', 'guest', 21);
        $this->counted('2026-10-30', 'app_open', 'account', 9);
        $this->counted('2026-10-31', 'app_open', 'guest', 18, '');
        $this->counted('2026-10-31', 'app_open', 'account', 3);
        $this->counted('2026-11-01', 'app_open', 'guest', 99); // today: not finished

        $this->actingAsAdmin();
        $this->getJson('/api/admin/reach')
            // The average of the 29th to the 31st.
            ->assertJsonPath('data.weeks.11', ['from' => '2026-10-26', 'to' => '2026-11-01', 'days' => 6, 'opens' => ['guest' => 27, 'account' => 6], 'devices' => null, 'reach' => null])
            ->assertJsonPath('data.weeks.10.opens', ['guest' => 0, 'account' => 0])
            ->assertJsonPath('data.days.166', ['day' => '2026-10-31', 'opens' => ['guest' => 18, 'account' => 3], 'devices' => null])
            ->assertJsonPath('data.today', ['day' => '2026-11-01', 'opens' => ['guest' => 99, 'account' => 0], 'devices' => null]);
    }

    public function test_the_app_may_send_the_once_a_period_kinds_and_only_an_admin_reads_them(): void
    {
        config(['counts.enabled' => true]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_day'], ['kind' => 'app_week'], ['kind' => 'app_month']], 'signedIn' => false])->assertNoContent();
        $this->assertSame(['app_day', 'app_month', 'app_week'], DB::table('usage_counts')->where('audience', 'guest')->orderBy('kind')->pluck('kind')->all());

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/reach')->assertForbidden();
    }
}
