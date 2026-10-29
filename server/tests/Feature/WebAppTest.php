<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BetaSinger;
use App\Models\EarlyAccessLink;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\EarlyAccess;
use App\Support\Http\Hosts;
use App\Support\Pages\MudeoArrival;
use App\Support\Web\AppShell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Laravel serves the PWA on our one origin (docs/architecture.md, "The system").
 */
final class WebAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_routes_answer_with_the_pwas_index(): void
    {
        foreach (['/?app', '/p/0b8f4c2e-1111-4a5b-9c3d-2e7f8a9b0c1d', '/sing', '/admin', '/admin/people'] as $path) {
            $response = $this->get($path);

            $response->assertOk()->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, private');
            $this->assertShell($response->getContent(), $path);
        }
    }

    public function test_before_launch_passers_by_see_coming_soon_but_invite_links_and_the_admin_work(): void
    {
        config(['app.coming_soon' => true]);

        $soon = $this->get('/')->assertOk()->assertSee(__('meta.coming_soon.title'))->assertSee('href="/signin"', false);
        // Never the app shell, which the service worker caches for offline use.
        $soon->assertDontSee('id="app"', false);
        $this->get('/sing')->assertSee(__('meta.coming_soon.title'));
        $this->assertShell($this->get('/admin')->getContent());
        $this->assertShell($this->get('/signin')->getContent());
        $this->assertShell($this->get('/verify?token=x')->getContent());
        // The emailed sign-in link, on a device that has never had the pass.
        $this->assertShell($this->get('/signin/link?token=x')->getContent());

        // Neither /beta, a made-up invite, nor a session cookie (any API call sets one) opens it.
        foreach (['/beta', '/beta/a-made-up-token', '/early/nope', '/join/nope', '/choir/nope'] as $path) {
            $this->get($path)->assertSee(__('meta.coming_soon.title'));
        }
        $this->withCookie((string) config('session.cookie'), 'x')->get('/')->assertSee(__('meta.coming_soon.title'));

        // An open beta invite opens it, and gives the pass that opens it from then on.
        BetaSinger::factory()->withToken('an-open-invite-token-0000000000000000001')->create(['expires_at' => now()->addDays(14)]);
        $invite = $this->get('/beta/an-open-invite-token-0000000000000000001');
        $this->assertShell($invite->getContent());
        $invite->assertHeader('Referrer-Policy', 'no-referrer');
        $pass = collect($invite->headers->getCookies())->first(fn ($c) => $c->getName() === BetaPass::COOKIE);
        $this->assertNotNull($pass);
        $this->assertTrue($pass->isHttpOnly());
        $this->assertShell($this->withUnencryptedCookie(BetaPass::COOKIE, (string) $pass->getValue())->get('/?app')->getContent());
        $this->withUnencryptedCookie(BetaPass::COOKIE, '9999999999.'.str_repeat('0', 64))->get('/sing')->assertSee(__('meta.coming_soon.title'));
    }

    public function test_the_preview_link_and_signing_in_give_the_beta_pass(): void
    {
        config(['app.coming_soon' => true]);
        $this->getJson('/api/auth/config')->assertJsonPath('data.comingSoon', true)->assertJsonPath('data.prelaunch', true)->assertHeader('Cache-Control', 'no-store, private');
        $admin = $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/preview');
        $this->assertMatchesContract($shown, 'admin.preview.show', 200);
        // Signed in, any API answer carries the pass.
        $this->assertNotNull(collect($shown->headers->getCookies())->first(fn ($c) => $c->getName() === BetaPass::COOKIE));
        $url = (string) $shown->json('data.url');
        $this->assertStringContainsString('/preview/', $url);
        $this->assertMatchesContract($this->patchJson('/api/admin/preview', ['paused' => true]), 'admin.preview.update', 200);
        $paused = $this->get($url);
        $paused->assertOk()->assertSee(__('meta.coming_soon.title'));
        $this->patchJson('/api/admin/preview', ['paused' => false])->assertOk();
        $this->app['auth']->forgetGuards();

        // Opening it (or a link preview fetching it) shows one button and counts nothing.
        $this->get($url)->assertOk()->assertSee(__('meta.preview.button'))->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertSame(0, EarlyAccess::current(EarlyAccessLink::PREVIEW)->joined);
        $opened = $this->post($url)->assertRedirect('/?app');
        $pass = collect($opened->headers->getCookies())->first(fn ($c) => $c->getName() === BetaPass::COOKIE);
        $this->assertNotNull($pass);
        // Not from mudeo: the preview never marks the visit as mudeo's (only the email's early link does).
        $this->assertNull(collect($opened->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::COOKIE));
        // Let in by the pass, the app isn't in coming-soon mode for them, and coming back again
        // goes straight in without counting twice.
        $this->withCredentials()->withUnencryptedCookie(BetaPass::COOKIE, (string) $pass->getValue())->getJson('/api/auth/config')->assertJsonPath('data.comingSoon', false)->assertJsonPath('data.prelaunch', true);
        $this->withUnencryptedCookie(BetaPass::COOKIE, (string) $pass->getValue())->get($url)->assertRedirect('/?app');
        $this->withUnencryptedCookie(BetaPass::COOKIE, (string) $pass->getValue())->post($url)->assertRedirect('/?app');
        $this->assertSame(1, EarlyAccess::current(EarlyAccessLink::PREVIEW)->joined);

        // A preview code never lets a sign-up through paused sign-ups.
        $code = substr($url, strrpos($url, '/') + 1);
        $this->assertFalse(EarlyAccess::opens($code));

        $this->actingAsAdmin($admin);
        $replaced = $this->postJson('/api/admin/preview/replace');
        $this->assertMatchesContract($replaced, 'admin.preview.replace', 200);
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertSee(__('meta.coming_soon.title'));
    }

    public function test_loading_the_app_sets_no_cookie_and_makes_no_session(): void
    {
        $response = $this->get('/sing');

        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_the_shell_names_the_theme_cookies_domain_only_when_it_is_shared(): void
    {
        config(['hosts.pass_domain' => null]);
        $this->assertStringNotContainsString('theme-cookie-domain', (string) $this->get('/sing')->getContent());

        config(['hosts.pass_domain' => '.stackapella.com']);
        $this->assertMatchesRegularExpression('#<meta name="theme-cookie-domain" content="\.stackapella\.com">\s*<script src="/js/theme-boot\.js"></script>#', (string) $this->get('/sing')->getContent());
    }

    public function test_the_shell_names_the_public_site_and_the_logo_reaches_the_landing(): void
    {
        $this->assertStringContainsString('<meta name="public-url" content="'.Hosts::publicUrl().'">', (string) $this->get('/sing')->getContent());

        // Someone who has used the app gets the app at /, and the landing at /?landing.
        $this->assertStringContainsString('name="public-url"', (string) $this->withUnencryptedCookie('sa_app', '1')->get('/')->getContent());
        $this->withUnencryptedCookie('sa_app', '1')->get('/?landing')->assertViewIs('pages.landing');
    }

    public function test_the_app_may_compile_templates_and_use_the_microphone_but_the_admin_is_never_framed(): void
    {
        $csp = (string) $this->get('/?app')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertNotEmpty(app(AppShell::class)->cdnSources());
        foreach (app(AppShell::class)->cdnSources() as $package) {
            $this->assertMatchesRegularExpression('#^https://cdn\.jsdelivr\.net/npm/.+@\d+\.\d+\.\d+/$#', $package);
            $this->assertStringContainsString($package, $csp);
        }
        $this->assertDoesNotMatchRegularExpression('#https://cdn\.jsdelivr\.net[ ;]#', $csp, 'Never the whole CDN host.');
        $this->assertStringNotContainsString("'unsafe-inline'", str_replace("style-src-attr 'unsafe-inline'", '', $csp));
        foreach (app(AppShell::class)->scriptHashes() as $hash) {
            $this->assertStringContainsString($hash, $csp, 'The import map is allowed by its hash.');
        }
        $this->assertStringContainsString('microphone=(self)', (string) $this->get('/?app')->headers->get('Permissions-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);

        $admin = $this->get('/admin');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $admin->headers->get('Content-Security-Policy'));
        $admin->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_import_map_is_hashed(): void
    {
        $this->assertNotEmpty(app(AppShell::class)->scriptHashes());
    }

    public function test_the_apps_files_are_served_but_not_its_tests_or_tools(): void
    {
        $this->get('/js/main.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8')->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, public')->assertHeader('Cloudflare-CDN-Cache-Control', 'no-store');
        $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $this->get('/sounds/mic-ready.mp3')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->get('/sw.js')->assertOk()->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, public');
        $this->get('/templates/index.json')->assertOk();

        // Each answers with the app (or a 404), never the file itself.
        foreach (['/tool/serve.php', '/js/../tool/serve.php', '/js/%2e%2e/tests/index.html', '/js/./../tests/harness.js', '/tests/index.html', '/js/..%2f..%2fserver/.env'] as $path) {
            $this->assertNotInstanceOf(BinaryFileResponse::class, $this->get($path)->baseResponse, "{$path} served a file from outside the app's folders.");
        }
        $this->assertInstanceOf(BinaryFileResponse::class, $this->get('/js/main.js')->baseResponse);
        $this->get('/js/missing.js')->assertNotFound();
    }

    public function test_an_unknown_api_path_is_a_json_404_with_its_code(): void
    {
        $this->getJson('/api/nothing-here')->assertNotFound()->assertExactJson(['message' => 'Not Found.', 'code' => 'not_found']);
        $this->get('/api/nothing-here')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_the_api_never_sends_a_document_policy(): void
    {
        $this->assertSame("default-src 'none'; frame-ancestors 'none'", $this->getJson('/api/admin/me')->headers->get('Content-Security-Policy'));
    }

    public function test_the_service_worker_may_fetch_what_it_precaches(): void
    {
        // It runs under its own script's policy: 'self' alone failed every new version's install.
        $csp = (string) $this->get('/sw.js')->assertOk()->headers->get('Content-Security-Policy');
        $connect = (string) collect(explode(';', $csp))->first(fn (string $d): bool => str_starts_with(trim($d), 'connect-src'));

        $this->assertStringContainsString('https://cdn.jsdelivr.net', $connect);
        $this->assertStringContainsString('https://fonts.gstatic.com', $connect);

        // And neither it nor the shell is ever kept: not by the browser, not at Cloudflare's edge.
        foreach (['/sw.js', '/sing'] as $path) {
            $response = $this->get($path)->assertOk();
            $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'), $path);
            $this->assertSame('no-store', $response->headers->get('Cloudflare-CDN-Cache-Control'), $path);
        }
    }

    public function test_the_basic_security_headers_are_on_every_response(): void
    {
        foreach (['/', '/admin/sign-in', '/api/admin/me', '/up'] as $path) {
            $response = $this->get($path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        $this->get('https://stackapella.test/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_emails_links_pass_the_gate_and_never_send_a_referrer(): void
    {
        config(['app.coming_soon' => true]);

        foreach (['/verify?token=x', '/reset?token=x&email=a', '/email/confirm?token=x', '/email/revert?token=x'] as $page) {
            $response = $this->get($page);
            $response->assertDontSee(__('meta.coming_soon.title'));
            $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'), $page);
        }
    }

    public function test_no_other_site_is_allowed_to_read_the_api(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/auth/config');

        $response->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->call('OPTIONS', '/api/reminders/devices/x', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH'])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_the_app_may_sign_in_with_google_or_apple_and_show_turnstile(): void
    {
        $csp = (string) $this->get('/?app')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("form-action 'self' https://accounts.google.com https://appleid.apple.com", $csp);
        $this->assertStringContainsString('frame-src https://challenges.cloudflare.com', $csp);
        $this->assertMatchesRegularExpression('/script-src [^;]*https:\/\/challenges\.cloudflare\.com/', $csp);
    }
}
