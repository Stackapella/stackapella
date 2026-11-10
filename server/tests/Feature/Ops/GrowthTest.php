<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Counts\RecordCounts;
use App\Console\Commands\OpsDigest;
use App\Models\StoreSubscription;
use App\Support\Site\SitePages;
use App\Support\Stats\ReportDay;
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
        $this->send([['kind' => 'f_arrive']], 'p-ana_sings');
        $this->send([['kind' => 'f_arrive']], 'john-smith');
        $this->send([['kind' => 'f_arrive']], null);

        $sources = DB::table('usage_counts')->where('kind', 'f_arrive')->orderBy('source')->pluck('source')->all();
        $this->assertSame(['', 'coach', 'harmony', 'hn', 'other', 'profile'], $sources);
        // No tag as sent, ever; a sourced row keeps no country; other kinds keep none of it.
        $this->assertSame(0, DB::table('usage_counts')->whereIn('source', ['h-Ab12Cd34', 'c-coach-ana', 'p-ana_sings', 'john-smith'])->count());
        $this->assertSame('', DB::table('usage_counts')->where('kind', 'site_visit')->value('country'));
        $this->assertSame('hn', DB::table('usage_counts')->where('kind', 'site_visit')->value('source'));
        $this->assertSame('', DB::table('usage_counts')->where('kind', 'app_open')->value('source'));
    }

    public function test_a_free_tools_link_is_its_own_source_and_the_digest_names_it(): void
    {
        $this->send([['kind' => 'f_arrive']], 'tool-tuner');
        $this->send([['kind' => 'f_arrive']], 'TOOL-Metronome');
        // Not one of our tools: grouped, never kept as sent.
        $this->send([['kind' => 'f_arrive']], 'tool-ana-smith');

        $this->assertSame(['other', 'tool-metronome', 'tool-tuner'], DB::table('usage_counts')->where('kind', 'f_arrive')->orderBy('source')->pluck('source')->all());

        // The longest tool's tag fits whole (usage_counts.source was once 16 characters).
        $longest = 'tool-'.collect((array) config('tools.slugs'))->sortByDesc(static fn (string $slug): int => strlen($slug))->first();
        $this->send([['kind' => 'f_arrive']], $longest);
        $this->assertSame(1, DB::table('usage_counts')->where('source', $longest)->count());
        // Longer than the request allows: refused whole, never a failed insert.
        $this->withHeader('CF-IPCountry', 'GB')->postJson('/api/counts', ['events' => [['kind' => 'f_arrive']], 'signedIn' => false, 'source' => 'tool-'.str_repeat('a', 70)])->assertUnprocessable();

        // Five or more arrivals, so the digest names it rather than folding it.
        DB::table('usage_counts')->where('kind', 'f_arrive')->where('source', 'tool-tuner')->increment('count', 6);
        $this->assertStringStartsWith('Where new visitors came from: Tools: tuner 7 (0 signed up)', (string) OpsDigest::sources(ReportDay::today()));
    }

    public function test_an_embedded_tool_counts_by_the_embedding_sites_host_and_nothing_else(): void
    {
        $this->send([['kind' => 'f_arrive']], 'embed-Choir.Example.org');
        $this->send([['kind' => 'f_arrive']], 'embed-'.str_repeat('a', 50).'.org');
        // Not a site's host: grouped, never kept as sent.
        $this->send([['kind' => 'f_arrive']], 'embed-localhost');
        $this->send([['kind' => 'f_arrive']], 'embed-<b>x</b>.org');

        $this->assertSame(['embed-'.str_repeat('a', 50).'.org', 'embed-choir.example.org', 'other'], DB::table('usage_counts')->where('kind', 'f_arrive')->orderBy('source')->pluck('source')->all());
        $this->assertSame(2, (int) DB::table('usage_counts')->where('source', 'other')->sum('count'));

    }

    public function test_every_campaign_tag_counts_end_to_end(): void
    {
        // Each of our own tags as a visitor brings it, through the real request.
        foreach ((array) config('counts.sources') as $tag) {
            $this->send([['kind' => 'f_arrive']], $tag);
            $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'f_arrive')->where('source', $tag)->sum('count'), $tag);
        }
    }

    public function test_every_tools_ref_counts_end_to_end(): void
    {
        // Each tool's link as a visitor brings it, through the real request (a long one once failed
        // the whole request: the column was 16 characters).
        foreach ((array) config('tools.slugs') as $slug) {
            $this->send([['kind' => 'f_arrive']], RecordCounts::TOOL_PREFIX.$slug);
            $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'f_arrive')->where('source', RecordCounts::TOOL_PREFIX.$slug)->sum('count'), $slug);
        }
    }

    public function test_every_live_tool_is_counted(): void
    {
        $tools = defined(SitePages::class.'::TOOLS') ? (array) constant(SitePages::class.'::TOOLS') : [];
        $live = array_is_list($tools) ? $tools : array_keys($tools);

        $this->assertSame([], array_values(array_diff($live, (array) config('tools.slugs'))), 'A live tool missing from config tools.slugs: its ?ref=tool-<slug> would count as "other".');
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
        // Noon in the report zone (UTC here): every comparison is cut at 12:00 on its own day, and with no
        // hours counted for those days and no records to shape a day by, half a day's total by noon.
        config(['admin.report_timezone' => 'UTC']);
        $this->travelTo(now()->utc()->setTime(12, 0));
        $row = fn (int $ago, string $kind, int $n, string $source = ''): array => ['day' => now()->utc()->subDays($ago)->toDateString(), 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n];
        DB::table('usage_counts')->insert([
            $row(0, 'f_arrive', 10), $row(0, 'f_arrive_start', 2, 'hn'), $row(1, 'f_arrive', 8), $row(7, 'f_arrive', 5), $row(8, 'f_arrive', 99), $row(90, 'f_arrive', 7),
            $row(0, 'site_visit', 40), $row(3, 'site_visit', 60), $row(0, 'sign_up', 3), $row(0, 'plus_started', 1),
        ]);
        $this->actingAsAdmin();

        // Today so far against yesterday by noon (8 x 0.5) and the same weekday last week by noon (5 x 0.5).
        $today = $this->getJson('/api/admin/growth');
        $this->assertMatchesContract($today, 'admin.growth.show', 200);
        $today->assertJsonPath('data.tiles.0', ['key' => 'arrivals', 'today' => 12, 'yesterday' => 4, 'lastWeek' => 3, 'current' => 12, 'previous' => 4, 'weekAgo' => 3, 'lowSample' => true])
            ->assertJsonPath('data.steps.0', ['key' => 'visits', 'total' => 40])->assertJsonPath('data.steps.2', ['key' => 'arrivals', 'total' => 12]);
        $this->getJson('/api/admin/growth?days=7')->assertJsonPath('data.steps.0.total', 100)->assertJsonPath('data.steps.2.total', 20)->assertJsonPath('data.tiles.0.today', 12);

        // 7 days: six whole days and today, against six whole days and a day cut at noon (99 + 2.5).
        $week = $this->getJson('/api/admin/growth?days=7');
        $this->assertMatchesContract($week, 'admin.growth.show', 200);
        $week->assertJsonPath('data.tiles.0.current', 20)->assertJsonPath('data.tiles.0.previous', 102)->assertJsonPath('data.tiles.0.weekAgo', 102)->assertJsonPath('data.tiles.0.lowSample', false)
            ->assertJsonPath('data.tiles.2', ['key' => 'signedUp', 'today' => 3, 'yesterday' => 0, 'lastWeek' => 0, 'current' => 3, 'previous' => 0, 'weekAgo' => 0, 'lowSample' => true]);

        // 12 weeks: the 84 days to today, the 84 before, and the same 84 a week back, each cut at noon.
        $weeks = $this->getJson('/api/admin/growth?days=84');
        $this->assertMatchesContract($weeks, 'admin.growth.show', 200);
        $weeks->assertJsonPath('data.from', now()->utc()->subDays(83)->toDateString())
            ->assertJsonPath('data.tiles.0.current', 124)->assertJsonPath('data.tiles.0.previous', 7)->assertJsonPath('data.tiles.0.weekAgo', 109);
    }

    public function test_sources_are_most_arrivals_first_with_the_small_ones_folded_last(): void
    {
        $row = fn (string $kind, int $n, string $source): array => ['day' => now()->utc()->toDateString(), 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n];
        DB::table('usage_counts')->insert([
            $row('f_arrive', 30, ''), $row('f_arrive', 50, 'hn'), $row('f_lesson1', 20, 'hn'), $row('f_course_lesson', 26, 'hn'), $row('f_account', 4, 'hn'), $row('site_visit', 90, 'hn'),
            $row('f_arrive', 3, 'harmony'), $row('f_arrive', 1, 'coach'), $row('f_day2', 1, 'coach'),
        ]);
        $this->actingAsAdmin();

        $sources = $this->getJson('/api/admin/sources');
        $this->assertMatchesContract($sources, 'admin.sources.show', 200);
        $this->assertSame(['hn', 'none', 'small'], array_column((array) $sources->json('data.sources'), 'source'));
        $sources->assertJsonPath('data.sources.0', ['source' => 'hn', 'visits' => 90, 'arrivals' => 50, 'lesson1' => 20, 'courseLesson' => 26, 'signedUp' => 4, 'day2' => 0, 'week1' => 0])
            ->assertJsonPath('data.sources.2', ['source' => 'small', 'sources' => 2, 'visits' => 0, 'arrivals' => 4, 'lesson1' => 0, 'courseLesson' => 0, 'signedUp' => 0, 'day2' => 1, 'week1' => 0]);
    }
}
