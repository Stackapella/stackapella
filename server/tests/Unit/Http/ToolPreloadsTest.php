<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Support\Site\SitePages;
use App\Support\Site\ToolPreloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A tool page names its module's whole import graph for modulepreload (web/js/public/tools/preloads.json):
 * only our own /js/ modules, [] when the file or the module isn't there.
 */
final class ToolPreloadsTest extends TestCase
{
    // Cache::remember writes to the database cache: in a transaction, so nothing outlives the test.
    use RefreshDatabase;

    private string $web;

    protected function setUp(): void
    {
        parent::setUp();
        $this->web = sys_get_temp_dir().'/stk-web-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->web.'/js/public/tools');
        config(['app.web_path' => $this->web]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->web);
        parent::tearDown();
    }

    public function test_a_modules_graph_and_only_our_own_modules(): void
    {
        $this->assertSame([], ToolPreloads::for('/js/public/tools/tuner.js'));

        File::put($this->web.ToolPreloads::FILE, (string) json_encode([
            '/js/public/tools/tuner.js' => ['/js/public/tools/tuner.js', '/js/public/live-mic.js', '/js/public/live-mic.js', 'https://evil.example/x.js', '/js/../server/.env.js', '/css/site.css', 42],
            'https://evil.example/y.js' => ['/js/a.js'],
        ]));
        Cache::flush();

        $this->assertSame(['/js/public/tools/tuner.js', '/js/public/live-mic.js'], ToolPreloads::for('/js/public/tools/tuner.js'));
        $this->assertSame([], ToolPreloads::for('/js/public/tools/metronome.js'));
        $this->assertSame([], ToolPreloads::for('https://evil.example/y.js'));
        $this->assertSame(['/js/public/tools/tuner.js', '/js/public/live-mic.js'], SitePages::tool('tuner')['preloads']);
    }

    public function test_a_broken_file_gives_nothing(): void
    {
        File::put($this->web.ToolPreloads::FILE, '{not json');
        Cache::flush();

        $this->assertSame([], ToolPreloads::for('/js/public/tools/tuner.js'));
    }
}
