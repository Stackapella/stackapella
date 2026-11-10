<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Site\SubmitToIndexNow;
use App\Models\IndexNowUrl;
use App\Models\PageVersion;
use App\Support\Flags;
use App\Support\Site\SitemapPaths;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * IndexNow (Hillel, 2026-11-09: "Yes, build it"): behind its switch, our own public pages that are
 * new to the sitemap or whose words changed go in one batch, at most the day's cap, with the key
 * served at /<key>.txt; a failure waits, never a loop; never self-hosted.
 */
final class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://site.stackapella.test';

    private const KEY = 'test-indexnow-key-0123';

    /** @var list<int> IndexNow's next answers, 202 once they run out */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 09:50:00', 'UTC'));
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => self::SITE, 'services.indexnow.key' => self::KEY, 'services.indexnow.daily_cap' => 500]);
        Http::fake(['api.indexnow.org/*' => fn () => Http::response('', array_shift($this->answers) ?? 202)]);
    }

    private function switchOn(): void
    {
        app(Flags::class)->set('indexnow', 'on', null, 'Hillel: yes, build it');
    }

    /**
     * @return list<string>
     */
    private function batch(): array
    {
        $this->artisan('app:indexnow')->assertSuccessful();
        $sent = [];
        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            $sent = [...$sent, ...(array) $request->data()['urlList']];
        }

        return $sent;
    }

    public function test_the_key_file_answers_only_its_own_name_on_the_public_site(): void
    {
        $this->get(self::SITE.'/'.self::KEY.'.txt')->assertOk()->assertSee(self::KEY, false)->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->get(self::SITE.'/someone-elses-key.txt')->assertNotFound();
        $this->get(self::SITE.'/robots.txt')->assertOk()->assertSee('User-agent');

        // Unset, the key is worked out from APP_KEY, the same on every call.
        config(['services.indexnow.key' => null]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', SubmitToIndexNow::key());
        $this->assertSame(SubmitToIndexNow::key(), SubmitToIndexNow::key());
        $this->get(self::SITE.'/'.SubmitToIndexNow::key().'.txt')->assertOk();

        config(['selfhost.enabled' => true]);
        $this->get(self::SITE.'/'.SubmitToIndexNow::key().'.txt')->assertNotFound();
    }

    public function test_nothing_goes_while_the_switch_is_off_or_self_hosted(): void
    {
        $this->assertSame([], $this->batch());
        $this->assertSame('off', app(Flags::class)->value('indexnow'));

        $this->switchOn();
        config(['selfhost.enabled' => true]);
        $this->assertSame([], $this->batch());
        Http::assertNothingSent();
    }

    public function test_new_pages_go_once_in_one_polite_batch_and_a_changed_one_goes_again(): void
    {
        $this->switchOn();
        $paths = app(SitemapPaths::class)->all();

        $sent = $this->batch();

        $this->assertSame(array_map(static fn (string $p): string => self::SITE.$p, array_values(array_unique($paths))), $sent);
        Http::assertSentCount(1);
        Http::assertSent(static fn (Request $r): bool => $r->url() === 'https://api.indexnow.org/indexnow' && $r->method() === 'POST'
            && $r->header('User-Agent') === ['StackapellaIndexNow/1.0'] && $r->header('Cookie') === []
            && $r['host'] === 'site.stackapella.test' && $r['key'] === self::KEY && $r['keyLocation'] === self::SITE.'/'.self::KEY.'.txt');

        // Nothing new: nothing sent.
        $this->assertSame(count($sent), count($this->batch()));
        Http::assertSentCount(1);

        // A page's words change (its lastmod moves): it alone goes again.
        PageVersion::factory()->create(['path' => '/pricing', 'changed_at' => now()]);
        $this->travel(1)->hours();
        $before = count(Http::recorded());
        $this->batch();
        $this->assertCount($before + 1, Http::recorded());
        $this->assertSame([self::SITE.'/pricing'], Http::recorded()[$before][0]->data()['urlList']);
    }

    public function test_the_days_cap_holds_the_rest_for_tomorrow_and_a_page_that_left_is_forgotten(): void
    {
        $this->switchOn();
        config(['services.indexnow.daily_cap' => 3]);
        $all = count(array_unique(app(SitemapPaths::class)->all()));
        IndexNowUrl::factory()->create(['path' => '/songs/back-in-the-queue']);

        $this->assertCount(3, $this->batch());
        $this->assertSame(0, IndexNowUrl::query()->where('path', '/songs/back-in-the-queue')->count());
        $this->travel(1)->hours();
        $this->batch();
        Http::assertSentCount(1);

        $this->travelTo(Carbon::parse('2026-11-11 00:50:00', 'UTC'));
        $this->batch();
        Http::assertSentCount(2);
        $this->assertSame(min(6, $all), IndexNowUrl::query()->count());
    }

    public function test_a_refused_batch_is_logged_and_waits_hours_never_a_loop(): void
    {
        $this->switchOn();
        $this->answers = [429];
        Log::spy();

        $this->batch();
        $this->assertSame(0, IndexNowUrl::query()->count());
        Log::shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'IndexNow refused'))->once();

        $this->travel(1)->hours();
        $this->batch();
        Http::assertSentCount(1);

        $this->travel(6)->hours();
        $this->batch();
        Http::assertSentCount(2);
        $this->assertGreaterThan(0, IndexNowUrl::query()->count());
    }

    public function test_it_runs_hourly_and_never_self_hosted(): void
    {
        $event = collect(app(Schedule::class)->events())->first(static fn ($e): bool => str_contains((string) $e->command, 'app:indexnow'));

        $this->assertNotNull($event);
        $this->assertSame('50 * * * *', $event->expression);
        $this->assertTrue($event->filtersPass(app()));
        config(['selfhost.enabled' => true]);
        $this->assertFalse($event->filtersPass(app()));
    }
}
