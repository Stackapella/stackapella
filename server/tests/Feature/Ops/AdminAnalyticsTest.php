<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\CheckoutSession;
use App\Models\CoachingPayment;
use App\Models\Engagement;
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
 * Admin analytics (docs/admin-analytics-plan.md, slices 1 and 2): active singers kept once a day
 * and drawn week by week, and revenue, each number from factories with known totals.
 */
final class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));
    }

    public function test_active_singers_are_kept_once_a_day_and_drawn_against_the_twelve_weeks_before(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['created_at' => now()->subDays(10)]);
        $c = User::factory()->create(['created_at' => now()->subDays(60)]);
        // A sang 2 days ago; B practised 10 days ago; C sang 40 days ago and practised 3 days ago.
        Take::factory()->create(['recorded_by' => $a->id, 'created_at' => now()->subDays(2)]);
        Score::factory()->create(['user_id' => $b->id, 'created_at' => now()->subDays(10)]);
        Take::factory()->create(['recorded_by' => $c->id, 'created_at' => now()->subDays(40)]);
        Score::factory()->create(['user_id' => $c->id, 'created_at' => now()->subDays(3)]);

        $this->artisan('app:counts:active-singers')->expectsOutput('Filled 168 days.')->assertSuccessful();
        // Each day once: a second run has nothing to do, and a day already kept isn't changed.
        Score::factory()->create(['user_id' => User::factory()->create()->id, 'created_at' => Carbon::parse('2026-10-22 10:00:00', 'UTC')]);
        $this->artisan('app:counts:active-singers')->expectsOutput('Filled 0 days.')->assertSuccessful();
        $this->assertSame(168, DB::table('active_singer_days')->count());

        $this->actingAsAdmin();
        $active = $this->getJson('/api/admin/active');
        $this->assertMatchesContract($active, 'admin.active.show', 200);
        // Now, live: the late score counts in the last 30 days.
        $active->assertJsonPath('data.week', 2)->assertJsonPath('data.month', 4)
            // The 7 days ending yesterday: A and C; the 30: all three.
            ->assertJsonPath('data.weeks.11', ['to' => '2026-10-30', 'week' => 2, 'month' => 3])
            // The week before: only B (the late score's day was already kept); C's take of 40 days ago is
        // outside its 30 days.
            ->assertJsonPath('data.weeks.10', ['to' => '2026-10-23', 'week' => 1, 'month' => 1])
            ->assertJsonPath('data.weeks.0.to', '2026-08-14')
            ->assertJsonPath('data.previous.0', ['to' => '2026-05-22', 'week' => 0, 'month' => 0])
            ->assertJsonPath('data.previous.11.to', '2026-08-07');
        // C's take, in the 30 days ending 2026-10-02 to 2026-10-20.
        $this->assertSame(1, DB::table('active_singer_days')->where('day', '2026-10-02')->value('month'));

        // A day that isn't worked out yet is null, not 0.
        DB::table('active_singer_days')->where('day', '2026-10-30')->delete();
        $this->getJson('/api/admin/active')->assertJsonPath('data.weeks.11', ['to' => '2026-10-30', 'week' => null, 'month' => null]);

        // Admin home's cards: the 7 days before, for the change (B joined then; B and the late score).
        $overview = $this->getJson('/api/admin/overview');
        $this->assertMatchesContract($overview, 'admin.overview', 200);
        $overview->assertJsonPath('data.growth.activeSingers', 2)
            ->assertJsonPath('data.growth.previous', ['signUps' => 1, 'upgrades' => 0, 'activeSingers' => 2]);
    }

    public function test_revenue_counts_each_currency_apart_and_never_prices_a_store_subscription(): void
    {
        config(['billing.prices' => ['month' => 'price_month', 'year' => 'price_year']]);
        $web = function (?string $currency, string $plan, string $status, string $created, ?string $trialEnds = null, ?string $ends = null): void {
            $user = User::factory()->create();

            if ($currency !== null) {
                CheckoutSession::factory()->create(['user_id' => $user->id, 'currency' => $currency, 'interval' => $plan, 'completed_at' => $created]);
                // A later Checkout in another currency doesn't change the first one's.
                CheckoutSession::factory()->create(['user_id' => $user->id, 'currency' => 'eur', 'completed_at' => $created]);
            }

            DB::table('subscriptions')->insert(['user_id' => $user->id, 'type' => 'default', 'stripe_id' => 'sub_'.Str::random(12), 'stripe_status' => $status, 'stripe_price' => 'price_'.$plan, 'quantity' => 1,
                'trial_ends_at' => $trialEnds, 'ends_at' => $ends, 'created_at' => $created, 'updated_at' => $created]);
        };
        $web('usd', 'month', 'active', '2026-06-10');                                  // paying, 499
        $web('usd', 'year', 'active', '2026-10-01', '2026-10-08');                     // paying since its trial, 3999 / 12
        $web('gbp', 'month', 'active', '2026-09-05', null, '2026-11-15');              // cancelling, still billed: 449
        $web('usd', 'month', 'trialing', '2026-10-28', '2026-11-04');                  // trialing, not billed yet
        $web('usd', 'month', 'past_due', '2026-08-01');                                // past due, still billed: 499
        $web('usd', 'month', 'canceled', '2026-07-01', null, '2026-09-20');            // lost in September
        $web('usd', 'year', 'canceled', '2026-10-01', '2026-10-08', '2026-10-08');     // a trial that ended: never paid
        $web(null, 'month', 'active', '2026-10-15');                                   // no Checkout: currency unknown
        StoreSubscription::factory()->create(['interval' => 'year', 'created_at' => '2026-10-20']);
        StoreSubscription::factory()->create(['store' => StoreSubscription::GOOGLE, 'interval' => 'month', 'will_renew' => false, 'created_at' => '2026-09-01']);
        StoreSubscription::factory()->create(['status' => StoreSubscription::EXPIRED, 'created_at' => '2026-05-01', 'expires_at' => '2026-08-15']);
        StoreSubscription::factory()->create(['environment' => 'Sandbox', 'created_at' => '2026-10-02']);

        // Coaching and packs: a lesson with a refund, a pack, and two that aren't ours to count.
        $lesson = Engagement::factory()->create(['kind' => Engagement::LESSON, 'currency' => 'usd']);
        CoachingPayment::factory()->create(['engagement_id' => $lesson->id, 'cents' => 5000, 'fee_cents' => 750, 'currency' => 'usd', 'paid_at' => '2026-10-03']);
        CoachingPayment::factory()->create(['engagement_id' => $lesson->id, 'kind' => CoachingPayment::REFUND, 'ref' => 'refund:x', 'cents' => -1000, 'fee_cents' => -150, 'currency' => 'usd', 'paid_at' => '2026-10-05']);
        CoachingPayment::factory()->create(['engagement_id' => Engagement::factory()->create(['kind' => Engagement::PACK])->id, 'cents' => 1800, 'fee_cents' => 270, 'currency' => 'GBP', 'paid_at' => '2026-10-04']);
        CoachingPayment::factory()->create(['cents' => 9999, 'fee_cents' => 0, 'currency' => 'usd', 'backfilled' => true, 'paid_at' => '2026-10-04']);
        CoachingPayment::factory()->create(['cents' => 2500, 'currency' => 'usd', 'paid_at' => '2025-10-20']);

        $this->actingAsAdmin();
        $revenue = $this->getJson('/api/admin/revenue');
        $this->assertMatchesContract($revenue, 'admin.revenue.show', 200);
        $revenue->assertJsonPath('data.mrr', [['currency' => 'gbp', 'cents' => 449], ['currency' => 'usd', 'cents' => 1331]])
            ->assertJsonPath('data.arr', [['currency' => 'gbp', 'cents' => 5388], ['currency' => 'usd', 'cents' => 15975]])
            ->assertJsonPath('data.monthAgo', [['currency' => 'gbp', 'cents' => 449], ['currency' => 'usd', 'cents' => 998]])
            // The unknown currency and both running store subscriptions.
            ->assertJsonPath('data.unpriced', 3)
            ->assertJsonPath('data.subscribers', [
                ['state' => 'paying', 'store' => 'apple', 'plan' => 'year', 'count' => 1],
                ['state' => 'paying', 'store' => 'web', 'plan' => 'month', 'count' => 2],
                ['state' => 'paying', 'store' => 'web', 'plan' => 'year', 'count' => 1],
                ['state' => 'trialing', 'store' => 'web', 'plan' => 'month', 'count' => 1],
                ['state' => 'pastDue', 'store' => 'web', 'plan' => 'month', 'count' => 1],
                ['state' => 'cancelling', 'store' => 'google', 'plan' => 'month', 'count' => 1],
                ['state' => 'cancelling', 'store' => 'web', 'plan' => 'month', 'count' => 1],
            ])
            ->assertJsonPath('data.months.0', ['month' => '2025-11', 'mrr' => [], 'unpriced' => 0, 'new' => 0, 'lost' => 0])
            ->assertJsonPath('data.months.6', ['month' => '2026-05', 'mrr' => [], 'unpriced' => 1, 'new' => 1, 'lost' => 0])
            ->assertJsonPath('data.months.7', ['month' => '2026-06', 'mrr' => [['currency' => 'usd', 'cents' => 499]], 'unpriced' => 1, 'new' => 1, 'lost' => 0])
            ->assertJsonPath('data.months.9', ['month' => '2026-08', 'mrr' => [['currency' => 'usd', 'cents' => 1497]], 'unpriced' => 0, 'new' => 1, 'lost' => 1])
            ->assertJsonPath('data.months.10', ['month' => '2026-09', 'mrr' => [['currency' => 'gbp', 'cents' => 449], ['currency' => 'usd', 'cents' => 998]], 'unpriced' => 1, 'new' => 2, 'lost' => 1])
            ->assertJsonPath('data.months.11', ['month' => '2026-10', 'mrr' => [['currency' => 'gbp', 'cents' => 449], ['currency' => 'usd', 'cents' => 1331]], 'unpriced' => 3, 'new' => 3, 'lost' => 0])
            ->assertJsonPath('data.oneOff', [
                ['month' => '2026-10', 'kind' => 'coaching', 'currency' => 'usd', 'payments' => 1, 'gross' => 4000, 'ours' => 600],
                ['month' => '2026-10', 'kind' => 'packs', 'currency' => 'gbp', 'payments' => 1, 'gross' => 1800, 'ours' => 270],
            ]);
    }

    public function test_only_an_admin_reads_them(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/active')->assertForbidden();
        $this->getJson('/api/admin/revenue')->assertForbidden();
    }
}
