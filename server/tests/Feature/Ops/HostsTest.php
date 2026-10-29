<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\Harmony;
use App\Models\User;
use App\Support\Accounts\BetaPass;
use App\Support\Http\Hosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The app's host and the public site's (docs/app-subdomain-plan.md): each page on its own host.
 */
final class HostsTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'cors.allowed_origins' => [self::SITE]]);
    }

    public function test_one_host_is_the_app_and_the_other_the_public_site(): void
    {
        $this->assertTrue(Hosts::split());

        // The landing is the public site's root; the app's root is the app.
        $this->get(self::SITE.'/')->assertOk()->assertViewIs('pages.landing')->assertSee(self::APP.'/learn/1-comfortable', false);
        $this->assertShell($this->get(self::APP.'/')->getContent());

        // Public pages on the app's host, and app pages on the public site, are sent across.
        $this->get(self::APP.'/pricing?currency=eur')->assertStatus(301)->assertRedirect(self::SITE.'/pricing?currency=eur');
        $this->get(self::APP.'/h/abc123def456')->assertRedirect(self::SITE.'/h/abc123def456');
        // An app page an old install might open shows the moving page, which brings its songs along.
        $this->get(self::SITE.'/p/0b8f4c2e-1111-4a5b-9c3d-2e7f8a9b0c1d')->assertOk()->assertViewIs('public.moving')->assertViewHas('appUrl', self::APP.'/p/0b8f4c2e-1111-4a5b-9c3d-2e7f8a9b0c1d')->assertHeader('X-Robots-Tag', 'noindex');
        $this->get(self::SITE.'/admin')->assertRedirect(self::APP.'/admin');
        $this->post(self::SITE.'/auth/google')->assertStatus(308);
        $this->get(self::SITE.'/verify?token=x')->assertRedirect(self::APP.'/verify?token=x');
        // An old install's start (/?app) is the app, and the public pages' files load on both.
        $this->get(self::SITE.'/?app')->assertOk()->assertViewIs('public.moving')->assertViewHas('appUrl', self::APP.'/?app');
        $this->assertNotSame(301, $this->get(self::SITE.'/css/app.css')->baseResponse->getStatusCode());

        // Either host: health, and (until old installs have moved) the API on the public site.
        $this->get(self::SITE.'/up')->assertSuccessful();
        $this->get(self::APP.'/up')->assertSuccessful();
        $this->getJson(self::SITE.'/api/auth/config')->assertOk();
        $this->getJson(self::APP.'/api/auth/config')->assertOk();
    }

    public function test_after_launch_the_old_public_host_keeps_old_installs_and_webhooks_and_sends_pages_on(): void
    {
        $old = 'https://beta.stackapella.test';
        config(['hosts.old_public_hosts' => ['beta.stackapella.test'], 'ops.indexing' => true]);

        // Its public pages, the landing and shared links go to the public site, path and all.
        $this->get($old.'/')->assertStatus(301)->assertRedirect(self::SITE.'/');
        $this->get($old.'/h/abc123def456?ref=x')->assertStatus(301)->assertRedirect(self::SITE.'/h/abc123def456?ref=x');
        $this->get($old.'/pricing')->assertRedirect(self::SITE.'/pricing');

        // An old install still opens there: the moving page (its songs are in that origin's
        // storage), and the API answers on it until it has moved.
        $this->get($old.'/?app')->assertOk()->assertViewIs('public.moving')->assertViewHas('appUrl', self::APP.'/?app');
        $this->get($old.'/p/0b8f4c2e-1111-4a5b-9c3d-2e7f8a9b0c1d')->assertOk()->assertViewIs('public.moving');
        $this->getJson($old.'/api/auth/config')->assertOk();
        $this->get($old.'/admin')->assertRedirect(self::APP.'/admin');

        // The webhooks pointed at it keep answering (a POST is never redirected), and health.
        $this->assertNotContains($this->post($old.'/webhooks/stripe')->getStatusCode(), [301, 302, 307, 308]);
        $this->get($old.'/up')->assertSuccessful();

        // Search engines are kept off it.
        // ...but a link shared from it still gets its preview card.
        $robots = (string) $this->get($old.'/robots.txt')->getContent();
        $this->assertStringStartsWith("User-agent: *\nDisallow: /\n", $robots);
        $this->assertStringContainsString("User-agent: facebookexternalhit\n", $robots);
        $this->assertStringContainsString("Allow: /h/\n", $robots);
    }

    public function test_the_courses_pages_are_the_public_sites_and_get_and_head_agree(): void
    {
        $this->get(self::SITE.'/courses')->assertOk()->assertViewIs('site.courses');
        $this->get(self::SITE.'/courses/vibrato')->assertOk()->assertViewIs('site.course');
        $this->call('HEAD', self::SITE.'/courses/vibrato')->assertOk();
        $this->call('HEAD', self::SITE.'/pricing')->assertOk();
        // On the app's host they go to the public site, as every public page does.
        $this->get(self::APP.'/courses/vibrato')->assertStatus(301)->assertRedirect(self::SITE.'/courses/vibrato');
    }

    public function test_every_public_site_route_is_classed_as_the_public_sites(): void
    {
        // A route the public site serves (the `nexus` middleware) that HostRouting doesn't know
        // as public gets the moving page there: the launch's /courses/<slug> bug.
        $missing = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('nexus', $route->gatherMiddleware(), true)) {
                continue;
            }
            $path = (string) preg_replace('/\{[^}]+\}/', 'x', $route->uri());
            if (! Str::is(Hosts::PUBLIC_PATHS, $path)) {
                $missing[] = $route->uri();
            }
        }
        $this->assertSame([], $missing, 'Add these to Hosts::PUBLIC_PATHS.');
    }

    public function test_links_point_at_the_right_host(): void
    {
        $owner = User::factory()->create();
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->getJson(self::APP.'/api/harmonies/'.$harmony->public_id)->assertJsonPath('data.pageUrl', self::SITE.'/h/'.$harmony->public_id);
        $this->assertSame(self::APP.'/learn/1-comfortable', Hosts::app('/learn/1-comfortable'));
        $this->assertSame(self::APP.'/verify?token=x', url('/verify?token=x'));
    }

    public function test_robots_and_the_sitemap_belong_to_the_public_site(): void
    {
        config(['ops.indexing' => true]);

        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get(self::APP.'/robots.txt')->getContent());
        $site = (string) $this->get(self::SITE.'/robots.txt')->getContent();
        $this->assertStringContainsString('Sitemap: '.self::SITE.'/sitemap.xml', $site);
        $this->assertStringContainsString('<loc>'.self::SITE.'/pricing</loc>', (string) $this->get(self::SITE.'/sitemap.xml')->getContent());
    }

    public function test_report_works_from_the_public_site_across_origins_and_nowhere_else(): void
    {
        $preflight = $this->call('OPTIONS', self::APP.'/api/reports', [], [], [], ['HTTP_ORIGIN' => self::SITE, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type']);
        $preflight->assertHeader('Access-Control-Allow-Origin', self::SITE);
        // Any other origin is never allowed (the one allowed origin is named, so a browser refuses it).
        $evil = $this->call('OPTIONS', self::APP.'/api/reports', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST']);
        $this->assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->call('OPTIONS', self::APP.'/api/projects', [], [], [], ['HTTP_ORIGIN' => self::SITE, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])->assertHeaderMissing('Access-Control-Allow-Origin');

        // Without the app's XSRF cookie, as a page on the other origin sends it.
        $this->withHeaders(['Origin' => self::SITE])->postJson(self::APP.'/api/reports', ['harmonyId' => 'nope', 'reason' => 'spam'])->assertNotFound()->assertHeader('Access-Control-Allow-Origin', self::SITE);

        // The public pages may reach the app's host for it.
        $this->assertStringContainsString("connect-src 'self' ".self::APP, (string) $this->get(self::SITE.'/pricing')->headers->get('Content-Security-Policy'));
    }

    public function test_before_launch_the_public_root_shows_the_landing_only_to_pass_holders(): void
    {
        config(['app.coming_soon' => true]);

        $this->get(self::SITE.'/')->assertOk()->assertSee(__('meta.coming_soon.title'))->assertSee(self::APP.'/signin', false);
        $pass = BetaPass::cookie(request());
        $this->withUnencryptedCookie(BetaPass::COOKIE, (string) $pass->getValue())->get(self::SITE.'/')->assertViewIs('pages.landing');

        // The public site's other pages answer as before.
        $this->get(self::SITE.'/pricing')->assertOk();

        config(['hosts.pass_domain' => '.stackapella.test']);
        $this->assertSame('.stackapella.test', BetaPass::cookie(request())->getDomain());
    }
}
