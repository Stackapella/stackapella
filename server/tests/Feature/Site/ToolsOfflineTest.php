<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The free tools offline and installable (docs/tools-plan.md): each tool's own manifest, and the
 * tools' own service worker beside the app's, served from either host and never kept at the edge.
 */
final class ToolsOfflineTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    public function test_each_tool_installs_as_its_own_app_and_an_unknown_one_is_not_found(): void
    {
        $manifest = $this->get('/tools/vocal-range-test/manifest.webmanifest')->assertOk();

        $this->assertStringStartsWith('application/manifest+json', (string) $manifest->headers->get('Content-Type'));
        $manifest->assertJson([
            'name' => 'Vocal range test by Stackapella',
            'short_name' => 'Vocal range test',
            'id' => '/tools/vocal-range-test',
            'start_url' => '/tools/vocal-range-test',
            'scope' => '/tools/vocal-range-test',
            'display' => 'standalone',
        ]);
        $this->assertContains(['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'], (array) $manifest->json('icons'));

        // Our branding: the name says Stackapella, and the colours are the site's own before anyone picks a
        // theme (Stage dark, web/js/theme-boot.js); once open, the page's theme-color follows their theme.
        preg_match('/"stage-dark": "(#[0-9a-f]{6})"/', (string) file_get_contents(base_path('../web/js/theme-boot.js')), $stage);
        $this->assertSame([$stage[1] ?? '', $stage[1] ?? ''], [$manifest->json('background_color'), $manifest->json('theme_color')]);
        foreach ((array) config('tools.slugs') as $tool) {
            $each = $this->get('/tools/'.$tool.'/manifest.webmanifest')->assertOk();
            $this->assertSame(__('tools.names.'.$tool).' by Stackapella', $each->json('name'));
            $this->assertLessThanOrEqual(16, mb_strlen((string) $each->json('short_name')), $tool);
        }

        $this->get('/tools/not-a-tool/manifest.webmanifest')->assertNotFound();
        // Every tool counted has an install name.
        $this->assertSame((array) config('tools.slugs'), array_keys((array) __('tools.names')));
    }

    public function test_one_list_of_slugs_names_every_tool_everywhere(): void
    {
        $slugs = (array) config('tools.slugs');

        $this->assertSame($slugs, array_keys((array) __('tools.names')), 'lang/en/tools.php names every slug, in order.');
        $this->assertSame([], array_values(array_diff((array) config('tools.listening'), $slugs)), 'A listening tool that is not a tool.');

        // Marketing's lists (each once it exists): the live tools and their modules, all by these slugs.
        foreach (['TOOLS', 'TOOL_MOUNTS', 'TOOL_MODULES', 'TOOL_VARIANTS'] as $name) {
            $list = defined(SitePages::class.'::'.$name) ? (array) constant(SitePages::class.'::'.$name) : [];
            $keys = array_is_list($list) ? $list : array_keys($list);
            $this->assertSame([], array_values(array_diff($keys, $slugs)), "SitePages::{$name} has a slug that config tools.slugs doesn't.");
        }
    }

    public function test_the_tools_worker_is_served_on_the_public_host_and_kept_apart_from_the_apps(): void
    {
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE]);

        $worker = $this->get(self::SITE.'/tools-sw.js')->assertOk();
        $this->assertStringStartsWith('text/javascript', (string) $worker->headers->get('Content-Type'));
        $script = (string) file_get_contents(base_path('../web/tools-sw.js'));
        $this->assertStringContainsString("const PREFIX = 'tools-';", $script);
        $this->assertStringContainsString("!url.pathname.startsWith('/api/')", $script);

        // The tools' pages and their manifests are the public site's; the app's host sends them there.
        $this->get(self::APP.'/tools/tuner/manifest.webmanifest')->assertRedirect(self::SITE.'/tools/tuner/manifest.webmanifest');
        $this->get(self::SITE.'/tools/tuner/manifest.webmanifest')->assertOk();

        // The app's worker deletes only its own caches, so on one origin it never clears the tools'.
        $this->assertStringContainsString("key.startsWith('stackapella-') && key !== CACHE", (string) file_get_contents(base_path('../web/sw.js')));

        // In production nginx serves it like the app's worker: always revalidated, never at the edge.
        $this->assertStringContainsString('location ~ ^/(sw\.js|tools-sw\.js|manifest\.webmanifest)$ {', (string) file_get_contents(base_path('../.do/nginx.conf')));
    }
}
