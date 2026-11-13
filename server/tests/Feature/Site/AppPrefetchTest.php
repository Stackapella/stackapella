<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Site\AppPrefetch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app's first screen, fetched while a visitor reads the landing or a song's page (first-session review, item 3):
 * the app's own preload lists (tool/preload_list.py writes them into web/js/preload.js), on the app's host.
 */
final class AppPrefetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lists_are_the_apps_own(): void
    {
        config(['hosts.public_url' => '']);
        $landing = AppPrefetch::landing();
        $song = AppPrefetch::song();

        $source = (string) file_get_contents(dirname(base_path()).'/web/js/preload.js');
        // preload_list.py's own markers: what the tool writes between, so a change to it that would break this shows here.
        preg_match("#// preload_list\\.py: begin\\n.*?'(/[^']+)'#s", $source, $first);
        preg_match("#// preload_list\\.py: learn begin\\n.*?'(/[^']+)'#s", $source, $learn);
        $this->assertContains($learn[1], $landing, "the learn list's first module");
        $this->assertContains($first[1], $landing, "preload.js's first module");
        foreach (['/js/main.js', '/locales/lesson1/en.json', '/course/course.json'] as $path) {
            $this->assertContains($path, $landing);
        }
        $this->assertContains('/js/views/template.js', $song);
        $this->assertNotContains('/course/course.json', $song, "a song's page doesn't need the courses");
        $this->assertGreaterThan(30, count($landing));
        $this->assertSame($landing, array_values(array_unique($landing)));
        foreach ([...$landing, ...$song] as $url) {
            $this->assertMatchesRegularExpression('#^/[\w./-]+$#', $url);
        }
    }

    public function test_it_is_off_where_the_site_and_the_app_are_on_two_hosts(): void
    {
        // The app's files answer no CORS header to the site's origin: a cross-host prefetch is blocked with a console
        // error (production's launch smoke, 2026-11-13). Off there: no list and no module on the page.
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://stackapella.test']);
        $this->assertSame([[], []], [AppPrefetch::landing(), AppPrefetch::song()]);
        $page = (string) $this->get('https://stackapella.test/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-app-prefetch', $page);
        $this->assertStringNotContainsString('app-prefetch.js', $page);
    }

    public function test_the_landing_and_a_song_page_carry_their_lists_and_the_module(): void
    {
        // One host (a local server): the page's own paths, never another port's.
        config(['hosts.public_url' => '']);
        $this->assertContains('/js/main.js', AppPrefetch::landing());

        app(SyncOfficialTemplates::class)->handle();
        foreach (['/' => AppPrefetch::landing(), '/songs/amazing-grace' => AppPrefetch::song()] as $page => $urls) {
            $html = (string) $this->get($page)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<script type="application/json" data-app-prefetch>(.+?)</script>#', $html, $page);
            preg_match('#<script type="application/json" data-app-prefetch>(.+?)</script>#', $html, $m);
            $this->assertSame($urls, json_decode(html_entity_decode($m[1]), true), $page);
            $this->assertStringContainsString('<script type="module" src="/js/public/app-prefetch.js"></script>', $html, $page);
        }
    }
}
