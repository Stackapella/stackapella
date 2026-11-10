<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Support\Http\StaticVersions;
use App\Support\Site\CourseCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The public pages' ?v= files (.do/nginx.conf): nginx keeps a year only this build's exact hash.
 */
final class StaticVersionsTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test (committed,
    // they expired before OpsTest's cache prune and broke its count).
    use RefreshDatabase;

    private string $web;

    protected function setUp(): void
    {
        parent::setUp();
        $this->web = sys_get_temp_dir().'/stk-static-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->web.'/css');
        File::ensureDirectoryExists($this->web.'/js');
        File::put($this->web.'/css/site.css', 'body{}');
        File::put($this->web.'/js/theme-boot.js', '1');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->web);
        parent::tearDown();
    }

    public function test_the_map_keeps_a_year_only_each_files_exact_hash_and_skips_missing_ones(): void
    {
        $site = substr(hash('xxh128', 'body{}'), 0, 12);
        $this->assertSame($site, StaticVersions::of($this->web, '/css/site.css'));
        $this->assertSame('/css/site.css?v='.$site, StaticVersions::url($this->web.'/', '/css/site.css'));
        // A missing file is linked plainly and never kept.
        $this->assertSame('/css/courses.css', StaticVersions::url($this->web, '/css/courses.css'));

        $map = StaticVersions::nginxMap($this->web);
        $this->assertStringContainsString("map \"\$uri?\$arg_v\" \$static_cache {\n    default \"no-cache, max-age=0, must-revalidate\";\n    \"/js/theme-boot.js?", $map);
        $this->assertStringContainsString("\"/css/site.css?{$site}\" \"public, max-age=31536000, immutable\";", $map);
        $this->assertStringContainsString("\$static_edge {\n    default \"no-store\";", $map);
        $this->assertStringContainsString("\"/css/site.css?{$site}\" \"public, max-age=31536000\";", $map);
        $this->assertStringNotContainsString('courses.css', $map);

        // A changed file is a new address; the old one falls back to revalidating.
        File::put($this->web.'/css/site.css', 'body{color:red}');
        $this->assertStringNotContainsString($site, StaticVersions::nginxMap($this->web));
    }

    public function test_a_public_page_links_this_builds_hashes(): void
    {
        $web = (string) config('app.web_path');
        $page = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#^/css/site\.css\?v=[0-9a-f]{12}$#', StaticVersions::url($web, '/css/site.css'));
        $this->assertStringContainsString('<link rel="stylesheet" href="'.StaticVersions::url($web, '/css/site.css').'">', $page);
        $this->assertStringContainsString('<script src="'.StaticVersions::url($web, '/js/theme-boot.js').'"></script>', $page);
    }

    public function test_game_modes_sheet_is_named_for_the_boot_script_and_linked_right_after_site_css(): void
    {
        $web = (string) config('app.web_path');
        $page = (string) $this->get('/pricing')->assertOk()->getContent();

        // docs/css-split-plan.md, step 3: the address in a meta before theme-boot.js (which preloads it in game mode), and
        // game-css.js right after site.css (which links it there, so it cascades where its rules stood in the whole build).
        $meta = '<meta name="game-css" content="'.StaticVersions::url($web, '/css/site-game.css').'">';
        $boot = '<script src="'.StaticVersions::url($web, '/js/theme-boot.js').'"></script>';
        $site = '<link rel="stylesheet" href="'.StaticVersions::url($web, '/css/site.css').'">';
        $link = '<script src="'.StaticVersions::url($web, '/js/game-css.js').'"></script>';
        foreach ([$meta, $boot, $site, $link] as $tag) {
            $this->assertStringContainsString($tag, $page);
        }
        $this->assertLessThan(strpos($page, $boot), strpos($page, $meta));
        $this->assertSame(1, preg_match('#'.preg_quote($site, '#').'\s*'.preg_quote($link, '#').'#', $page), 'game-css.js not right after site.css');
        $this->assertStringNotContainsString('<link rel="stylesheet" href="'.StaticVersions::url($web, '/css/site-game.css'), $page);
    }

    public function test_the_layout_and_the_build_name_the_same_files(): void
    {
        $nginx = (string) file_get_contents(base_path('../.do/nginx.conf'));
        $docker = (string) file_get_contents(base_path('../.do/Dockerfile'));
        $layout = (string) file_get_contents(resource_path('views/public/layout.blade.php'));

        $this->assertStringContainsString('include /etc/nginx/static-versions.conf;', $nginx);
        $this->assertStringContainsString('add_header Cache-Control $static_cache always;', $nginx);
        $this->assertStringContainsString('StaticVersions::nginxMap("/app/web");\' > /etc/nginx/static-versions.conf', $docker);

        foreach (array_diff(StaticVersions::FILES, [CourseCss::WHOLE]) as $file) {
            $this->assertStringContainsString("StaticVersions::url(\$web, '{$file}')", $layout);
        }
        // courses.css is the course sheet's fallback (CourseCss::url): the layout links what that picks.
        $this->assertStringContainsString('CourseCss::url($web, ', $layout);
    }
}
