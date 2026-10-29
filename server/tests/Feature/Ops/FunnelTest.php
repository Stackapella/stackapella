<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The onboarding funnel (App\Actions\Counts\Funnel): firsts counted as daily totals per experiment
 * arm, with no country on an arm's rows, and Admin > Funnel's rates, split check and difference.
 */
final class FunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['counts.enabled' => true, 'proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true, 'counts.experiments' => [
            ['id' => 'onb1', 'arms' => ['a', 'b'], 'start' => now()->subDays(3)->toDateString(), 'end' => null, 'plannedPerArm' => 4, 'primary' => 'f_lesson3', 'guardrail' => 'f_lesson2'],
        ]]);
        Cache::forget('funnel.current');
    }

    /**
     * @param  list<string>  $kinds
     */
    private function send(array $kinds, ?string $variant, bool $signedIn = false): void
    {
        $this->withHeaders(['do-connecting-ip' => '172.70.1.2', 'cf-connecting-ip' => '198.51.100.7', 'cf-ipcountry' => 'NL'])->postJson('/api/counts', ['events' => array_map(fn (string $kind): array => ['kind' => $kind], $kinds), 'signedIn' => $signedIn, 'variant' => $variant])->assertNoContent();
    }

    public function test_a_variant_is_kept_only_on_funnel_kinds_it_names_and_then_without_a_country(): void
    {
        $this->send(['f_arrive', 'app_open', 'ask_install_shown', 'made_up'], 'onb1-b');
        $this->send(['f_arrive'], 'onb9-z');
        $this->send(['f_mic'], null);

        $rows = DB::table('usage_counts')->orderBy('kind')->orderBy('variant')->get(['kind', 'country', 'variant', 'count'])->map(fn (object $r): array => (array) $r)->all();
        $this->assertSame([
            ['kind' => 'app_open', 'country' => 'NL', 'variant' => '', 'count' => 1],
            ['kind' => 'ask_install_shown', 'country' => '', 'variant' => 'onb1-b', 'count' => 1],
            ['kind' => 'f_arrive', 'country' => 'NL', 'variant' => '', 'count' => 1],
            ['kind' => 'f_arrive', 'country' => '', 'variant' => 'onb1-b', 'count' => 1],
            ['kind' => 'f_mic', 'country' => 'NL', 'variant' => '', 'count' => 1],
        ], array_map(fn (array $r): array => [...$r, 'count' => (int) $r['count']], $rows));
    }

    public function test_the_app_learns_the_running_experiment_and_only_its_end_day_stops_enrolment(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiment', ['id' => 'onb1', 'arms' => ['a', 'b'], 'enrol' => true]);

        // A flood of arrivals (each a first, so one a request whatever n says) never ends it.
        foreach (range(1, 6) as $i) {
            $this->withHeaders(['cf-ipcountry' => 'NL'])->postJson('/api/counts', ['events' => [['kind' => 'f_arrive', 'n' => 20]], 'variant' => 'onb1-a'])->assertNoContent();
            $this->send(['f_arrive_start'], 'onb1-b');
        }
        $this->assertSame(6, (int) DB::table('usage_counts')->where('variant', 'onb1-a')->sum('count'));
        Cache::forget('funnel.current');
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiment.enrol', true);

        // Its end day passes: no new devices join; a week later it's over.
        config(['counts.experiments.0.end' => now()->subDay()->toDateString()]);
        Cache::forget('funnel.current');
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiment.enrol', false);
        $this->travel(8)->days();
        Cache::forget('funnel.current');
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiment', null);

        config(['counts.experiments' => [['id' => 'onb1', 'arms' => ['a', 'b'], 'start' => null, 'end' => null, 'plannedPerArm' => 4, 'primary' => 'f_lesson3', 'guardrail' => null]]]);
        Cache::forget('funnel.current');
        $this->getJson('/api/auth/config')->assertJsonPath('data.experiment', null);
    }

    public function test_admin_funnel_shows_each_arms_rates_the_split_and_the_difference(): void
    {
        $rows = [['f_arrive', 'onb1-a', 'guest', 380], ['f_arrive', 'onb1-a', 'account', 20], ['f_arrive', 'onb1-b', 'guest', 400],
            ['f_lesson3', 'onb1-a', 'guest', 100], ['f_lesson3', 'onb1-b', 'guest', 160], ['ask_install_shown', 'onb1-b', 'guest', 50], ['ask_install_yes', 'onb1-b', 'guest', 10],
            ['f_arrive', '', 'guest', 999]];
        foreach ($rows as [$kind, $variant, $audience, $count]) {
            DB::table('usage_counts')->insert(['day' => now()->toDateString(), 'kind' => $kind, 'audience' => $audience, 'country' => '', 'variant' => $variant, 'count' => $count]);
        }
        $this->actingAsAdmin();

        $shown = $this->getJson('/api/admin/funnel?experiment=onb1');
        $this->assertMatchesContract($shown, 'admin.funnel.show', 200);
        $shown->assertJsonPath('data.experiment', 'onb1')
            ->assertJsonPath('data.split.ok', true)
            ->assertJsonPath('data.enough', true)
            ->assertJsonPath('data.experiments.0.enrolling', true);

        $lesson3 = collect((array) $shown->json('data.steps'))->firstWhere('kind', 'f_lesson3');
        $this->assertSame([0.25, 0.4], array_column($lesson3['arms'], 'rate'));
        $this->assertSame(15.0, (float) $lesson3['difference']['points']);
        $this->assertGreaterThan(0, $lesson3['difference']['low']);
        $this->assertSame(0.2, collect((array) $shown->json('data.asks'))->firstWhere('ask', 'install')['arms'][1]['rate']);

        // Every device, no arms: the 999 without a variant counted too.
        $all = $this->getJson('/api/admin/funnel?days=7');
        $this->assertMatchesContract($all, 'admin.funnel.show', 200);
        $all->assertJsonPath('data.experiment', null)->assertJsonPath('data.split', null)->assertJsonPath('data.steps.1.arms.0.total', 1799);
    }

    public function test_a_lopsided_split_is_flagged(): void
    {
        DB::table('usage_counts')->insert([
            ['day' => now()->toDateString(), 'kind' => 'f_arrive', 'audience' => 'guest', 'country' => '', 'variant' => 'onb1-a', 'count' => 600],
            ['day' => now()->toDateString(), 'kind' => 'f_arrive', 'audience' => 'guest', 'country' => '', 'variant' => 'onb1-b', 'count' => 400],
        ]);
        $this->actingAsAdmin();

        $this->getJson('/api/admin/funnel?experiment=onb1')->assertJsonPath('data.split.ok', false);
    }

    public function test_the_funnel_is_an_admins_only(): void
    {
        $this->actingAsEditor();
        $this->getJson('/api/admin/funnel')->assertForbidden();
    }
}
