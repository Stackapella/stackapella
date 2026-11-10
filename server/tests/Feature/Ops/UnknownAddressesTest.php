<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Support\Http\AppRoutes;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * An address on the public site that is neither one of its pages nor one of the app's is a real 404
 * (search engines read a 200 as a page). An old link to an app page still gets "Stackapella has
 * moved"; one origin (a self-hosted install, local) keeps the app's shell for everything.
 */
final class UnknownAddressesTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    private function split(): void
    {
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_an_address_that_was_never_a_page_is_a_real_404_on_the_public_site(): void
    {
        $this->split();

        // /tools and the live tools are pages now (marketing's ToolPagesController); a tool that isn't live is still a 404.
        foreach (['/random-xyz-123', '/tools/nonsense', '/tools/drone-xyz', '/courses/nonsense', '/for/nobody', '/wp-admin/setup'] as $path) {
            $page = $this->get(self::SITE.$path)->assertNotFound()->assertSee(__('errors.not_found.title'));
            $this->assertStringNotContainsString('<div id="app">', (string) $page->getContent(), $path);
        }
        // A tool that's planned (config tools.slugs) but not live yet (SitePages::TOOLS, or a live variant) is a 404 too, until it launches.
        $planned = array_values(array_diff((array) config('tools.slugs'), SitePages::TOOLS, SitePages::variants()));
        if ($planned !== []) {
            $this->get(self::SITE.'/tools/'.$planned[0])->assertNotFound()->assertSee(__('errors.not_found.title'));
        }
        $this->assertSame('noindex', $this->get(self::SITE.'/random-xyz-123')->headers->get('X-Robots-Tag'));
        $this->call('HEAD', self::SITE.'/random-xyz-123')->assertNotFound();
    }

    public function test_an_old_link_to_an_app_page_still_moves_and_signing_in_still_goes_to_the_app(): void
    {
        $this->split();

        foreach (['/p/abc123', '/learn', '/learn/c/vibrato', '/settings', '/groups/xyz', '/?app'] as $path) {
            $this->get(self::SITE.$path)->assertOk()->assertSee('data-moving', false)->assertHeader('X-Robots-Tag', 'noindex');
        }
        $this->get(self::SITE.'/signin')->assertRedirect(self::APP.'/signin');
        // The site's own pages and the landing are as they were.
        $this->get(self::SITE.'/pricing')->assertOk();
        $this->get(self::SITE.'/')->assertOk();
    }

    public function test_one_origin_keeps_the_apps_shell_for_its_routes(): void
    {
        config(['hosts.public_url' => null, 'app.coming_soon' => false]);

        $this->get('/random-xyz-123?app')->assertOk()->assertSee('<div id="app">', false);
    }

    public function test_every_route_of_the_app_is_known_as_the_apps(): void
    {
        $router = (string) file_get_contents(base_path('../web/js/router.js'));
        preg_match_all('#pattern: /\^\\\\/([a-z-]*)#', $router, $m);
        $segments = array_values(array_unique(array_filter($m[1])));

        $this->assertGreaterThan(20, count($segments));
        foreach ($segments as $segment) {
            $known = AppRoutes::is(Request::create('/'.$segment)) || AppRoutes::is(Request::create('/'.$segment.'/x'));
            $this->assertTrue($known, "web/js/router.js has /{$segment}, which AppRoutes::PATHS doesn't list: on the public site it would be a 404 instead of the moving page.");
        }
    }
}
