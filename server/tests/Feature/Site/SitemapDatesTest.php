<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Console\Commands\RecordPageVersions;
use App\Models\PageVersion;
use App\Models\Template;
use App\Support\Pages\SongStories;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\TestCase;

/**
 * The sitemap's lastmod is when a page's words last changed: a deploy that changes none keeps the
 * date, a change of words moves it, a song's page is its current version's day, and a page never
 * read has none.
 */
final class SitemapDatesTest extends TestCase
{
    use RefreshDatabase;

    private function lastmod(string $path): ?string
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
        $loc = preg_quote(htmlspecialchars(rtrim((string) config('app.url'), '/').$path, ENT_XML1), '#');

        $this->assertMatchesRegularExpression('#<loc>'.$loc.'</loc>#', $xml, $path.' is in the sitemap');

        return preg_match('#<loc>'.$loc.'</loc><lastmod>([0-9-]+)</lastmod>#', $xml, $m) === 1 ? $m[1] : null;
    }

    public function test_a_page_is_dated_when_its_words_change_and_not_by_a_deploy(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 10:00:00', 'UTC'));
        $this->assertNull($this->lastmod('/about'));

        $this->artisan('app:sitemap:dates')->assertSuccessful();
        $this->assertSame('2026-11-02', $this->lastmod('/about'));
        $this->assertSame('2026-11-02', $this->lastmod('/pricing'));

        // A week on, a deploy (a new build, so new asset versions) with the same words: the date stays.
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));
        config(['app.build' => 'a-new-build']);
        $this->artisan('app:sitemap:dates')->assertSuccessful();
        $this->assertSame('2026-11-02', $this->lastmod('/about'));

        // About's words change: only its date moves.
        app('translator')->addLines(['site.about.lead' => 'Words that are new this week.'], 'en');
        $this->artisan('app:sitemap:dates')->assertSuccessful();
        $this->assertSame('2026-11-09', $this->lastmod('/about'));
        $this->assertSame('2026-11-02', $this->lastmod('/pricing'));
    }

    public function test_a_page_that_wont_render_is_named_and_the_rest_are_still_dated(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 02:20:00', 'UTC'));
        config(['app.build' => 'abc1234']);
        View::composer('site.about', static fn () => throw new RuntimeException('About broke'));

        $this->artisan('app:sitemap:dates')->expectsOutputToContain('1 skipped: /about (500).')->assertSuccessful();
        $this->assertSame('2026-11-08', $this->lastmod('/pricing'));
        $this->assertNull($this->lastmod('/about'));

        // The last run's summary, for the growth pull (no agent reads the deploy's log).
        $last = (array) Cache::get(RecordPageVersions::LAST);
        $this->assertSame(['2026-11-08T02:20:00Z', 'abc1234', 'finished', 1, ['/about (500)']], [$last['at'], $last['build'], $last['state'], $last['skipped'], $last['skippedPages']]);
        $this->assertSame([$last['of'] - 1, $last['of']], [$last['dated'], $last['read']]);
    }

    public function test_a_run_says_it_started_before_it_reads_a_page_so_one_that_dies_says_how_far_it_got(): void
    {
        // A page part way through looks at the summary while the run is reading it.
        $during = null;
        View::composer('site.about', static function () use (&$during): void {
            $during ??= Cache::get(RecordPageVersions::LAST);
        });

        $this->artisan('app:sitemap:dates')->assertSuccessful();

        $this->assertIsArray($during);
        $this->assertSame('started', $during['state']);
        $this->assertLessThan($during['of'], $during['read']);
        $this->assertSame('finished', Cache::get(RecordPageVersions::LAST)['state']);

        // A run that dies: the page it was on, and the fatal error's first line (a warning isn't what ended it).
        $summary = ['state' => 'started', 'read' => 140, 'of' => 387];
        $oom = ['type' => E_ERROR, 'message' => "Allowed memory size of 268435456 bytes exhausted\nmore", 'file' => 'x.php', 'line' => 1];
        $this->assertSame(['state' => 'died', 'read' => 140, 'of' => 387, 'page' => '/courses/vibrato', 'error' => 'Allowed memory size of 268435456 bytes exhausted'], RecordPageVersions::died($summary, '/courses/vibrato', $oom));
        $this->assertNull(RecordPageVersions::died($summary, '/about', ['type' => E_WARNING, 'message' => 'a warning', 'file' => 'x.php', 'line' => 1])['error']);
        $this->assertNull(RecordPageVersions::died($summary, null, null)['page']);
    }

    public function test_after_a_deploy_the_scheduler_dates_the_new_build_once(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 05:05:00', 'UTC'));

        // No build to go by (a developer's machine): nothing is read.
        config(['app.build' => null]);
        $this->artisan('app:sitemap:dates', ['--if-new-build' => true])->expectsOutputToContain('dated already')->assertSuccessful();
        $this->assertNull(Cache::get(RecordPageVersions::LAST));

        // A new build is read once; the next five-minute check leaves it.
        config(['app.build' => 'b94416c']);
        $this->artisan('app:sitemap:dates', ['--if-new-build' => true])->expectsOutputToContain('pages read')->assertSuccessful();
        $this->assertSame(['b94416c', 'finished'], [Cache::get(RecordPageVersions::LAST)['build'], Cache::get(RecordPageVersions::LAST)['state']]);
        $this->assertSame('2026-11-08', $this->lastmod('/about'));
        $this->artisan('app:sitemap:dates', ['--if-new-build' => true])->expectsOutputToContain('dated already')->assertSuccessful();

        // A run that began on this build and died isn't tried again every five minutes (the daily run still is).
        Cache::forever(RecordPageVersions::LAST, ['build' => 'c0ffee1', 'state' => 'died']);
        config(['app.build' => 'c0ffee1']);
        $this->artisan('app:sitemap:dates', ['--if-new-build' => true])->expectsOutputToContain('dated already')->assertSuccessful();
        $this->artisan('app:sitemap:dates')->expectsOutputToContain('pages read')->assertSuccessful();

        // Both are on the schedule: daily, and every five minutes for a new build.
        $events = collect(app(Schedule::class)->events())->filter(static fn (Event $e): bool => str_contains((string) $e->command, 'app:sitemap:dates'))
            ->mapWithKeys(static fn (Event $e): array => [(str_contains((string) $e->command, '--if-new-build') ? 'deploy' : 'daily') => $e->expression])->sortKeys()->all();
        $this->assertSame(['daily' => '20 4 * * *', 'deploy' => '*/5 * * * *'], $events);
    }

    public function test_a_song_page_is_its_current_versions_day_and_a_gone_page_is_forgotten(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $template = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        DB::table('template_versions')->where('id', $template->current_version_id)->update(['created_at' => '2026-10-20 23:30:00']);
        // A song page's day is its current version's, or its story's last change when that is later.
        $this->assertSame(max('2026-10-20', SongStories::updated('amazing-grace') ?? '2026-10-20'), $this->lastmod('/songs/amazing-grace'));

        PageVersion::factory()->create(['path' => '/no-longer-listed']);
        $this->artisan('app:sitemap:dates')->assertSuccessful();
        $this->assertFalse(PageVersion::query()->where('path', '/no-longer-listed')->exists());
        // Songs are dated by their version, never read for a hash.
        $this->assertFalse(PageVersion::query()->where('path', 'like', '/songs/%')->exists());
    }

    public function test_only_what_a_reader_sees_is_hashed(): void
    {
        $page = static fn (string $token, string $asset, string $words): string => '<html><head><title>About</title><meta name="description" content="Who we are">'
            .'<meta name="csrf-token" content="'.$token.'"><link rel="stylesheet" href="/css/app.css?v='.$asset.'"></head>'
            .'<body><header>Menu</header><main><h1>About</h1><p class="x" data-id="'.$token.'">'.$words.'</p><script>var b="'.$asset.'";</script></main></body></html>';

        $this->assertSame(RecordPageVersions::hash($page('aaa', '111', 'Hello there')), RecordPageVersions::hash($page('bbb', '222', "Hello\n  there")));
        $this->assertNotSame(RecordPageVersions::hash($page('aaa', '111', 'Hello there')), RecordPageVersions::hash($page('aaa', '111', 'Hello, there')));
    }
}
