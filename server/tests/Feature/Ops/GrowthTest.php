<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\StoreSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Plus;
use Tests\TestCase;

/**
 * Admin > Growth (docs/admin-growth-plan.md, slices 3 and 4): landing visits, Plus started, and
 * where people came from, as daily totals: a source is a short group, never the tag as sent.
 */
final class GrowthTest extends TestCase
{
    use Plus;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['counts.enabled' => true]);
    }

    /**
     * @param  list<array{kind: string}>  $events
     */
    private function send(array $events, ?string $source): void
    {
        $this->withHeader('CF-IPCountry', 'GB')->postJson('/api/counts', ['events' => $events, 'signedIn' => false, ...($source !== null ? ['source' => $source] : [])])->assertNoContent();
    }

    public function test_a_source_is_kept_only_as_its_group_on_the_funnel_and_landing_visits(): void
    {
        $this->send([['kind' => 'f_arrive'], ['kind' => 'site_visit'], ['kind' => 'app_open']], 'hn');
        $this->send([['kind' => 'f_arrive']], 'h-Ab12Cd34');
        $this->send([['kind' => 'f_arrive']], 'c-coach-ana');
        $this->send([['kind' => 'f_arrive']], 'john-smith');
        $this->send([['kind' => 'f_arrive']], null);

        $sources = DB::table('usage_counts')->where('kind', 'f_arrive')->orderBy('source')->pluck('source')->all();
        $this->assertSame(['', 'coach', 'harmony', 'hn', 'other'], $sources);
        // No tag as sent, ever; a sourced row keeps no country; other kinds keep none of it.
        $this->assertSame(0, DB::table('usage_counts')->whereIn('source', ['h-Ab12Cd34', 'c-coach-ana', 'john-smith'])->count());
        $this->assertSame('', DB::table('usage_counts')->where('kind', 'site_visit')->value('country'));
        $this->assertSame('hn', DB::table('usage_counts')->where('kind', 'site_visit')->value('source'));
        $this->assertSame('', DB::table('usage_counts')->where('kind', 'app_open')->value('source'));
    }

    public function test_plus_started_is_counted_once_when_a_subscription_starts_in_any_store(): void
    {
        $started = fn (): int => (int) DB::table('usage_counts')->where('kind', 'plus_started')->sum('count');

        $ana = $this->member();
        $this->subscribe($ana);
        $this->stripeEvent('customer.subscription.created', $this->subscription($ana))->assertOk();
        $this->assertSame(1, $started());

        // A store's: running is a start; a renewal out of grace isn't; after it ended, it is again.
        $store = StoreSubscription::factory()->create();
        $this->assertSame(2, $started());
        $store->forceFill(['status' => StoreSubscription::GRACE])->save();
        $store->forceFill(['status' => StoreSubscription::ACTIVE])->save();
        $this->assertSame(2, $started());
        $store->forceFill(['status' => StoreSubscription::EXPIRED])->save();
        $store->forceFill(['status' => StoreSubscription::ACTIVE])->save();
        $this->assertSame(3, $started());
        StoreSubscription::factory()->create(['environment' => 'Sandbox']);
        $this->assertSame(3, $started());

        // The app can't send it.
        $this->send([['kind' => 'plus_started']], null);
        $this->assertSame(3, $started());
    }

    public function test_the_overview_has_today_yesterday_and_last_week_and_each_steps_total(): void
    {
        $row = fn (int $ago, string $kind, int $n, string $source = ''): array => ['day' => now()->utc()->subDays($ago)->toDateString(), 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n];
        DB::table('usage_counts')->insert([
            $row(0, 'f_arrive', 10), $row(0, 'f_arrive_start', 2, 'hn'), $row(1, 'f_arrive', 8), $row(7, 'f_arrive', 5), $row(8, 'f_arrive', 99),
            $row(0, 'site_visit', 40), $row(3, 'site_visit', 60), $row(0, 'sign_up', 3), $row(0, 'plus_started', 1),
        ]);
        $this->actingAsAdmin();

        $today = $this->getJson('/api/admin/growth');
        $this->assertMatchesContract($today, 'admin.growth.show', 200);
        $today->assertJsonPath('data.tiles.0', ['key' => 'arrivals', 'today' => 12, 'yesterday' => 8, 'lastWeek' => 5])
            ->assertJsonPath('data.steps.0', ['key' => 'visits', 'total' => 40])->assertJsonPath('data.steps.2', ['key' => 'arrivals', 'total' => 12]);
        $this->getJson('/api/admin/growth?days=7')->assertJsonPath('data.steps.0.total', 100)->assertJsonPath('data.steps.2.total', 20)->assertJsonPath('data.tiles.0.today', 12);
    }

    public function test_sources_are_most_arrivals_first_with_the_small_ones_folded_last(): void
    {
        $row = fn (string $kind, int $n, string $source): array => ['day' => now()->utc()->toDateString(), 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n];
        DB::table('usage_counts')->insert([
            $row('f_arrive', 30, ''), $row('f_arrive', 50, 'hn'), $row('f_lesson1', 20, 'hn'), $row('f_account', 4, 'hn'), $row('site_visit', 90, 'hn'),
            $row('f_arrive', 3, 'harmony'), $row('f_arrive', 1, 'coach'), $row('f_day2', 1, 'coach'),
        ]);
        $this->actingAsAdmin();

        $sources = $this->getJson('/api/admin/sources');
        $this->assertMatchesContract($sources, 'admin.sources.show', 200);
        $this->assertSame(['hn', 'none', 'small'], array_column((array) $sources->json('data.sources'), 'source'));
        $sources->assertJsonPath('data.sources.0', ['source' => 'hn', 'visits' => 90, 'arrivals' => 50, 'lesson1' => 20, 'signedUp' => 4, 'day2' => 0, 'week1' => 0])
            ->assertJsonPath('data.sources.2', ['source' => 'small', 'sources' => 2, 'visits' => 0, 'arrivals' => 4, 'lesson1' => 0, 'signedUp' => 0, 'day2' => 1, 'week1' => 0]);
    }
}
