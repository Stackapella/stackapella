<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Ops\Check;
use App\Support\Ops\Readiness;
use App\Support\Ops\SitemapProblems;
use App\Support\Site\SitemapCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * app:sitemap:check's robots.txt reading and its command (the walk over the real site, with a broken
 * page of each kind seeded beside it, is SitemapHealthTest's).
 */
final class SitemapCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_rules_apply_the_longest_match_and_only_the_star_group(): void
    {
        $rules = SitemapCheck::robotsRules("User-agent: Twitterbot\nAllow: /h/\n\nUser-agent: *\nDisallow: /admin\nDisallow: /h/\nAllow: /h/open$\n# Disallow: /songs\n");

        $this->assertTrue(SitemapCheck::disallowed($rules, '/admin/people'));
        $this->assertTrue(SitemapCheck::disallowed($rules, '/h/abc'));
        $this->assertFalse(SitemapCheck::disallowed($rules, '/h/open'));
        $this->assertFalse(SitemapCheck::disallowed($rules, '/songs'));
    }

    public function test_the_command_asks_politely_and_says_each_kind_with_examples(): void
    {
        $asked = [];
        Http::fake(function ($request) use (&$asked) {
            $asked[] = $request;
            $url = $request->url();

            return match (true) {
                str_ends_with($url, '/robots.txt') => Http::response("User-agent: *\nDisallow: /admin\n"),
                str_ends_with($url, '/sitemap.xml') => Http::response('<urlset><url><loc>https://example.com/</loc></url><url><loc>https://example.com/gone</loc></url></urlset>'),
                str_ends_with($url, '/gone') => Http::response('', 404),
                str_ends_with($url, '/moved') => Http::response('', 302, ['Location' => 'https://example.com/']),
                default => Http::response('<html><head><link rel="canonical" href="https://example.com/"></head><body><a href="/moved">moved</a></body></html>'),
            };
        });

        $this->artisan('app:sitemap:check', ['--base' => 'https://example.com', '--delay' => 0])
            ->expectsOutputToContain("doesn't answer 200 (a redirect or an error): 1")
            ->expectsOutputToContain('https://example.com/gone (404)')
            ->expectsOutputToContain('https://example.com/moved (302 to https://example.com/, from https://example.com/)')
            ->assertFailed();

        // Each once, an error or a redirect never asked again.
        $this->assertCount(5, $asked);
        foreach ($asked as $request) {
            $this->assertSame(['StackapellaSitemapCheck/1.0'], $request->header('User-Agent'));
            $this->assertSame([], $request->header('Cookie'));
            $this->assertSame([], $request->header('Authorization'));
        }
    }

    public function test_the_weekly_run_keeps_what_it_found_for_readiness_and_admin_until_a_clean_run(): void
    {
        $gone = 7;
        Http::fake(function ($request) use (&$gone) {
            $url = $request->url();
            $locs = '<url><loc>https://example.com/</loc></url>';
            for ($i = 1; $i <= $gone; $i++) {
                $locs .= "<url><loc>https://example.com/gone-{$i}</loc></url>";
            }

            return match (true) {
                str_ends_with($url, '/robots.txt') => Http::response("User-agent: *\nDisallow: /admin\n"),
                str_ends_with($url, '/sitemap.xml') => Http::response("<urlset>{$locs}</urlset>"),
                str_contains($url, '/gone-') => Http::response('', 404),
                default => Http::response('<html><head><link rel="canonical" href="https://example.com/"></head></html>'),
            };
        });
        $sitemap = fn (): ?Check => collect(app(Readiness::class)->run())->firstWhere('key', 'sitemap');
        $this->assertSame('pass', $sitemap()?->status);

        $this->artisan('app:sitemap:check', ['--base' => 'https://example.com', '--delay' => 0, '--save' => true])->assertFailed();

        $this->assertCount(7, SitemapProblems::latest()['problems'] ?? []);
        $this->assertSame('fail', $sitemap()?->status);
        $this->assertStringContainsString('7 problems', (string) $sitemap()?->message);
        $this->actingAsAdmin();
        $overview = $this->getJson('/api/admin/overview');
        $this->assertMatchesContract($overview, 'admin.overview', 200);
        $shown = $overview->json('data.sitemapProblems');
        $this->assertCount(6, $shown);
        $this->assertSame("In the sitemap, but doesn't answer 200 (a redirect or an error): https://example.com/gone-1 (404)", $shown[0]);
        $this->assertSame('and 2 more (php artisan app:sitemap:check --all)', $shown[5]);

        // Without --save, a run changes nothing kept; a clean weekly run clears it.
        $gone = 0;
        $this->artisan('app:sitemap:check', ['--base' => 'https://example.com', '--delay' => 0])->assertSuccessful();
        $this->assertCount(7, SitemapProblems::latest()['problems'] ?? []);
        $this->artisan('app:sitemap:check', ['--base' => 'https://example.com', '--delay' => 0, '--save' => true])->assertSuccessful();
        $this->assertSame([], SitemapProblems::latest()['problems'] ?? null);
        $this->assertSame('pass', $sitemap()?->status);
        $this->getJson('/api/admin/overview')->assertJsonPath('data.sitemapProblems', []);
    }

    public function test_it_runs_on_wednesday_mornings_on_our_own_site_only(): void
    {
        $event = collect(app(Schedule::class)->events())->first(static fn ($e): bool => str_contains((string) $e->command, 'app:sitemap:check --save'));

        $this->assertNotNull($event);
        $this->assertSame('30 5 * * 3', $event->expression);
        $this->assertTrue($event->filtersPass(app()));
        config(['selfhost.enabled' => true]);
        $this->assertFalse($event->filtersPass(app()));
    }
}
