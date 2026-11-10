<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The security headers and Content-Security-Policy of each kind of page (docs/security-headers.md):
 * tight, but letting through what each page really loads (Google Fonts, the media CDN with the
 * real instruments, signed private media in the app, Turnstile in shield mode).
 */
final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'filesystems.disks.media.url' => 'https://media.stackapella.test',
            'filesystems.disks.private.driver' => 's3',
            'filesystems.disks.private.endpoint' => 'https://ams3.digitaloceanspaces.com',
            'filesystems.disks.private.bucket' => 'stackapella-private',
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function policy(TestResponse $response): array
    {
        $directives = [];

        foreach (array_filter(array_map('trim', explode(';', (string) $response->headers->get('Content-Security-Policy')))) as $directive) {
            $parts = preg_split('/\s+/', $directive) ?: [];
            $directives[(string) array_shift($parts)] = array_values($parts);
        }

        return $directives;
    }

    public function test_the_public_pages_run_only_our_scripts_and_load_fonts_and_media(): void
    {
        $page = $this->get('/pricing')->assertOk();
        $csp = self::policy($page);

        $this->assertSame(["'self'", 'https://challenges.cloudflare.com'], $csp['script-src']);
        $this->assertNotContains("'unsafe-eval'", $csp['script-src']);
        $this->assertNotContains("'unsafe-inline'", $csp['script-src']);
        $this->assertSame(["'self'"], $csp['style-src']);
        $this->assertSame(["'self'"], $csp['font-src']);
        $this->assertContains('https://media.stackapella.test', $csp['img-src']);
        $this->assertContains('https://media.stackapella.test', $csp['media-src']);
        // The landing's lane fetches a guide from the CDN to score it.
        $this->assertContains('https://media.stackapella.test', $csp['connect-src']);
        $this->assertSame(["'none'"], $csp['object-src']);
        $this->assertSame(["'self'"], $csp['frame-ancestors']);
        $page->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('microphone=()', (string) $page->headers->get('Permissions-Policy'));
        // Perfect your pitch's page lets a visitor try the pitch meter, the Course feature and
        // beginners' pages "Find your comfortable note", and the singing-in-tune guide Bullseye;
        // no other public page listens.
        foreach (['/courses/perfect-your-pitch', '/features/course', '/for/beginners', '/guides/singing-in-tune'] as $listens) {
            $this->assertStringStartsWith('microphone=(self)', (string) $this->get($listens)->headers->get('Permissions-Policy'), $listens);
        }
        $this->assertStringStartsWith('microphone=()', (string) $this->get('/features/studio')->headers->get('Permissions-Policy'));
        $this->assertStringStartsWith('microphone=()', (string) $this->get('/courses/vibrato')->headers->get('Permissions-Policy'));

        // The free tools: only the tuner and the range test listen (their pages are marketing's; once
        // /tools is a page, it and the other tools' pages say microphone=() like any other).
        foreach (['/tools/tuner', '/tools/vocal-range-test', '/tools/audio-latency-test', '/tools/mic-test', '/tools/pitch-matching', '/tools/just-intonation-tuner', '/tools/ukulele-tuner', '/tools/violin-tuner', '/tools/voice-tuner', '/tools/guitar-tuner', '/tools/bass-tuner', '/tools/cello-tuner', '/tools/viola-tuner', '/tools/mandolin-tuner', '/tools/banjo-tuner'] as $listens) {
            $this->assertStringStartsWith('microphone=(self)', (string) $this->get($listens)->headers->get('Permissions-Policy'), $listens);
        }
        $this->assertSame(['tools/tuner', 'tools/vocal-range-test', 'tools/audio-latency-test', 'tools/mic-test', 'tools/pitch-matching', 'tools/just-intonation-tuner', 'tools/ukulele-tuner', 'tools/violin-tuner', 'tools/voice-tuner', 'tools/guitar-tuner', 'tools/bass-tuner', 'tools/cello-tuner', 'tools/viola-tuner', 'tools/mandolin-tuner', 'tools/banjo-tuner', 'embed/tools/tuner'], SecurityHeaders::listeningTools());
    }

    public function test_the_app_allows_its_pinned_libraries_the_microphone_and_the_instruments(): void
    {
        $shell = $this->get('/sing')->assertOk();
        $csp = self::policy($shell);

        $this->assertContains("'unsafe-eval'", $csp['script-src']);
        // Only pinned package paths on the CDN, never its whole host.
        foreach ($csp['script-src'] as $source) {
            $this->assertFalse(str_starts_with($source, 'https://cdn.jsdelivr.net') && ! preg_match('#^https://cdn\.jsdelivr\.net/npm/[a-z0-9.-]+@\d+\.\d+\.\d+/$#', $source), "{$source} isn't one pinned package.");
        }
        $this->assertNotContains("'unsafe-inline'", $csp['script-src']);
        // The real instruments come from the media CDN; signed audio and cards from the private bucket.
        $this->assertContains('https://media.stackapella.test', $csp['connect-src']);
        $this->assertContains('https://stackapella-private.ams3.digitaloceanspaces.com', $csp['media-src']);
        $this->assertContains('https://stackapella-private.ams3.digitaloceanspaces.com', $csp['img-src']);
        $this->assertSame(["'self'"], $csp['font-src']);
        $this->assertStringStartsWith('microphone=(self)', (string) $shell->headers->get('Permissions-Policy'));
    }

    public function test_the_api_is_never_a_document_and_the_admin_is_never_framed(): void
    {
        $this->assertSame(['default-src' => ["'none'"], 'frame-ancestors' => ["'none'"]], self::policy($this->getJson('/api/auth/config')));

        $admin = $this->get('/admin/sign-in');
        $admin->assertHeader('X-Frame-Options', 'DENY');
        $this->assertSame(["'none'"], self::policy($admin)['frame-ancestors']);
        $this->assertSame(["'self'"], self::policy($admin)['script-src']);
    }

    public function test_https_answers_carry_hsts(): void
    {
        $this->get('https://stackapella.test/pricing')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
