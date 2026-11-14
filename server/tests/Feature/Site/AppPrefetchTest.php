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
        // Learn on a song's page opens Home with the song's sheet: Home's view and the sheet's code, before the tap.
        foreach (['/js/views/projects.js', '/js/components/dialogs.js'] as $path) {
            $this->assertContains($path, $song);
        }
        $this->assertNotContains('/course/course.json', $song, "a song's page doesn't need the courses");
        $this->assertGreaterThan(30, count($landing));
        $this->assertSame($landing, array_values(array_unique($landing)));
        // Vue from its CDN first, exactly as preload.js asks for it (so its version never drifts); then the app's own paths.
        preg_match("#preload\\('(https://cdn\\.jsdelivr\\.net/npm/vue@[^']+)'#", $source, $vue);
        $this->assertSame($vue[1], $landing[0]);
        $this->assertSame($vue[1], $song[0]);
        foreach ([...array_slice($landing, 1), ...array_slice($song, 1)] as $url) {
            $this->assertMatchesRegularExpression('#^/[\w./-]+$#', $url);
        }
    }

    public function test_on_two_hosts_it_asks_the_apps_host(): void
    {
        // As production: the site's page prefetches from the app's host, whose files answer any origin (the next test).
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://stackapella.test']);
        $this->assertContains('https://app.stackapella.test/js/main.js', AppPrefetch::landing());
        foreach ([...array_slice(AppPrefetch::landing(), 1), ...array_slice(AppPrefetch::song(), 1)] as $url) {
            $this->assertStringStartsWith('https://app.stackapella.test/', $url);
        }
        $this->assertStringStartsWith('https://cdn.jsdelivr.net/npm/vue@', AppPrefetch::landing()[0], 'the CDN keeps its own address');
        $page = (string) $this->get('https://stackapella.test/')->assertOk()->getContent();
        $this->assertStringContainsString('data-app-prefetch', $page);
        $this->assertStringContainsString('app-prefetch.js', $page);
    }

    public function test_the_apps_files_allow_the_sites_origin(): void
    {
        // The prefetch's other half: the app's host answers the site's crossorigin fetch of its modules, so the browser
        // doesn't block it. nginx in production, WebAppController here, each the same.
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://stackapella.test']);
        $this->get('https://app.stackapella.test/js/main.js', ['Origin' => 'https://stackapella.test'])->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        $nginx = (string) file_get_contents(dirname(base_path()).'/.do/nginx.conf');
        $this->assertSame(1, preg_match('#location ~ \^/\(js\|[^)]+\)/ \{(.*?)\n        \}#s', $nginx, $static), "nginx.conf's static location");
        $this->assertStringContainsString('add_header Access-Control-Allow-Origin "*" always;', $static[1]);
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
