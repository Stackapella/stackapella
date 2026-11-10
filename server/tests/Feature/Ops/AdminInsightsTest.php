<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\Part;
use App\Models\Project;
use App\Models\Score;
use App\Models\StoreSubscription;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin analytics slices 3 to 5 (docs/admin-analytics-plan.md): results by sign-up month, churn
 * and trials, and the activity page, each number from factories with known totals.
 */
final class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'UTC'));
    }

    private function sang(User $owner, string $song, string $at): void
    {
        $project = Project::factory()->create(['user_id' => $owner->id, 'template_slug' => $song, 'template_version' => 1]);

        for ($i = 0; $i < 2; $i++) {
            $part = Part::factory()->create(['project_id' => $project->id, 'order' => $i, 'template_part_key' => 'p'.$i]);
            $take = Take::factory()->create(['part_id' => $part->id, 'created_at' => Carbon::parse($at, 'UTC')]);
            $part->forceFill(['current_take_id' => $take->id])->save();
        }
    }

    private function subscription(string $status, string $created, ?string $trialEnds = null, ?string $ends = null): void
    {
        DB::table('subscriptions')->insert(['user_id' => User::factory()->create()->id, 'type' => 'default', 'stripe_id' => 'sub_'.Str::random(12), 'stripe_status' => $status, 'stripe_price' => 'price_month', 'quantity' => 1,
            'trial_ends_at' => $trialEnds, 'ends_at' => $ends, 'created_at' => $created, 'updated_at' => $created]);
    }

    public function test_each_sign_up_month_shows_who_finished_a_song_came_back_and_paid(): void
    {
        $a = User::factory()->create(['created_at' => '2026-10-05 10:00:00', 'plus_paid_at' => '2026-10-12 10:00:00']);
        $this->sang($a, 'amazing-grace', '2026-10-07 10:00:00'); // 2 days in
        $this->sang($a, 'danny-boy', '2026-10-10 10:00:00');     // a second song
        Score::factory()->create(['user_id' => $a->id, 'created_at' => '2026-11-10 10:00:00']); // active lately
        $b = User::factory()->create(['created_at' => '2026-10-20 10:00:00']);
        $this->sang($b, 'amazing-grace', '2026-10-21 10:00:00'); // 1 day in, and active
        User::factory()->create(['created_at' => '2026-10-25 10:00:00']);
        $d = User::factory()->create(['created_at' => '2026-11-02 10:00:00']);
        StoreSubscription::factory()->create(['user_id' => $d->id]);
        $this->actingAsAdmin()->forceFill(['created_at' => '2026-10-06 10:00:00'])->save();

        $cohorts = $this->getJson('/api/admin/cohorts');
        $this->assertMatchesContract($cohorts, 'admin.cohorts.show', 200);
        $cohorts->assertJsonCount(12, 'data.months')->assertJsonPath('data.timezone', 'UTC')
            ->assertJsonPath('data.months.0', ['month' => '2025-12', 'signedUp' => 0, 'finishedSong' => 0, 'secondSong' => 0, 'active' => 0, 'plus' => 0, 'medianDaysToFirstSong' => null])
            ->assertJsonPath('data.months.10', ['month' => '2026-10', 'signedUp' => 3, 'finishedSong' => 2, 'secondSong' => 1, 'active' => 2, 'plus' => 1, 'medianDaysToFirstSong' => 1.5])
            // The admins (one made in October, one signed in now) are never counted.
            ->assertJsonPath('data.months.11', ['month' => '2026-11', 'signedUp' => 1, 'finishedSong' => 0, 'secondSong' => 0, 'active' => 0, 'plus' => 1, 'medianDaysToFirstSong' => null]);
    }

    public function test_churn_counts_who_was_paying_who_left_by_store_and_which_trials_paid(): void
    {
        $this->subscription('canceled', '2026-09-01 00:00:00', null, '2026-10-15 00:00:00');                      // lost in October
        $this->subscription('active', '2026-10-01 00:00:00', '2026-10-08 00:00:00');                             // a trial that paid
        $this->subscription('canceled', '2026-10-10 00:00:00', '2026-10-17 00:00:00', '2026-10-17 00:00:00');   // a trial that didn't
        StoreSubscription::factory()->create(['store' => StoreSubscription::GOOGLE, 'status' => StoreSubscription::EXPIRED, 'created_at' => '2026-08-01', 'expires_at' => '2026-11-05']);

        $this->actingAsAdmin();
        $churn = $this->getJson('/api/admin/churn');
        $this->assertMatchesContract($churn, 'admin.churn.show', 200);
        $churn->assertJsonPath('data.months.10', ['month' => '2026-10', 'payingAtStart' => 2, 'lost' => ['web' => 1, 'apple' => 0, 'google' => 0], 'trialsEnded' => 2, 'trialsConverted' => 1])
            ->assertJsonPath('data.months.11', ['month' => '2026-11', 'payingAtStart' => 2, 'lost' => ['web' => 0, 'apple' => 0, 'google' => 1], 'trialsEnded' => 0, 'trialsConverted' => 0]);
    }

    public function test_the_activity_page_has_a_feed_the_last_day_by_hour_feature_use_and_limits(): void
    {
        $admin = $this->actingAsAdmin();
        $singer = User::factory()->create(['name' => 'Sam']);
        $this->subscription('canceled', '2026-09-01 00:00:00', null, '2026-10-15 00:00:00');
        StoreSubscription::factory()->create(['user_id' => $singer->id, 'store' => StoreSubscription::GOOGLE, 'created_at' => '2026-11-14 09:00:00']);
        // Two takes and a score in the last day; an admin's take isn't counted.
        $project = Project::factory()->create(['user_id' => $singer->id]);
        Take::factory()->count(2)->create(['part_id' => Part::factory()->create(['project_id' => $project->id])->id, 'created_at' => now()->subHours(2)]);
        Take::factory()->create(['recorded_by' => $admin->id, 'created_at' => now()->subHours(2)]);
        Score::factory()->create(['user_id' => $singer->id, 'created_at' => now()->subMinutes(30)]);
        DB::table('usage_counts')->insert([
            ['day' => '2026-11-05', 'kind' => 'harmonise_used', 'audience' => 'guest', 'country' => '', 'count' => 5],
            ['day' => '2026-11-05', 'kind' => 'harmonise_used', 'audience' => 'account', 'country' => '', 'count' => 2],
            ['day' => '2026-10-01', 'kind' => 'harmonise_used', 'audience' => 'guest', 'country' => '', 'count' => 99],
        ]);
        // Free accounts with 3 projects and 2.
        $full = User::factory()->create();
        Project::factory()->count(3)->create(['user_id' => $full->id]);
        Project::factory()->count(1)->create(['user_id' => $singer->id]); // the singer now has 2

        $activity = $this->getJson('/api/admin/activity');
        $this->assertMatchesContract($activity, 'admin.activity.show', 200);
        $kinds = array_column((array) $activity->json('data.feed'), 'kind');
        $this->assertContains('plus_ended', $kinds);
        $this->assertContains('sign_up', $kinds);
        $this->assertNotContains($admin->sqid(), array_column(array_filter(array_column((array) $activity->json('data.feed'), 'person')), 'id'));
        $store = collect((array) $activity->json('data.feed'))->firstWhere('kind', 'plus_started');
        $this->assertSame(['id' => $singer->sqid(), 'name' => 'Sam'], $store['person']);
        $this->assertSame('google', $store['detail']);

        $activity->assertJsonCount(24, 'data.hours')->assertJsonPath('data.hours.23.hour', '2026-11-15T12:00:00Z');
        $this->assertSame(2, array_sum(array_column((array) $activity->json('data.hours'), 'takes')));
        $this->assertSame(1, array_sum(array_column((array) $activity->json('data.hours'), 'scores')));
        $this->assertSame(['kind' => 'harmonise_used', 'guest' => 5, 'account' => 2], $activity->json('data.features.0'));
        // No free project limit since 2026-11-07 (storage is the limit): none is at it.
        $activity->assertJsonPath('data.limits.projectsAtLimit', 0)->assertJsonPath('data.limits.projectsNearLimit', 0)->assertJsonPath('data.limits.projectLimit', 0);
        config(['usage.plans.free.projects' => 3]);
        $this->getJson('/api/admin/activity')->assertJsonPath('data.limits.projectsAtLimit', 1)->assertJsonPath('data.limits.projectsNearLimit', 1)->assertJsonPath('data.limits.projectLimit', 3);
    }

    public function test_only_an_admin_reads_them(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['cohorts', 'churn', 'activity'] as $page) {
            $this->getJson('/api/admin/'.$page)->assertForbidden();
        }
    }
}
