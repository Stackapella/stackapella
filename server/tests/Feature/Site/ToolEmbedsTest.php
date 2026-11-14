<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\GamePreload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Free embeds of the tuner, metronome, drone and pitch pipe (docs/seo-growth-plan.md, item 2): any
 * site may frame them, they set no cookies, the tuner's may use the mic its framing page delegates,
 * and nothing else on the site can be framed by another site.
 */
final class ToolEmbedsTest extends TestCase
{
    use RefreshDatabase;

    private string $views;

    protected function tearDown(): void
    {
        if (isset($this->views)) {
            File::deleteDirectory($this->views);
        }
        parent::tearDown();
    }

    /** The embed's view, standing in: what it's given, as JSON. */
    private function withEmbed(): void
    {
        $this->views = sys_get_temp_dir().'/stk-views-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->views.'/embed');
        File::put($this->views.'/embed/tool.blade.php', '<pre id="embed">{{ json_encode($embed) }}</pre>');
        // In front of marketing's real view, so these tests read exactly what the embed is given.
        View::getFinder()->prependLocation($this->views);
        View::getFinder()->flush();
    }

    /** @return array<string, list<string>> */
    private function csp(TestResponse $response): array
    {
        $directives = [];

        foreach (explode(';', (string) $response->headers->get('Content-Security-Policy')) as $directive) {
            $parts = preg_split('/\s+/', trim($directive)) ?: [];
            if (($name = array_shift($parts)) !== null && $name !== '') {
                $directives[$name] = $parts;
            }
        }

        return $directives;
    }

    public function test_an_embed_only_for_a_tool_offered(): void
    {
        // The view exists now (marketing's embed/tool.blade.php); before it did, every embed was a 404.
        $this->get('/embed/tools/tuner')->assertOk();

        $this->withEmbed();
        $this->get('/embed/tools/vocal-range-test')->assertNotFound();
        $this->get('/embed/tools/nonsense')->assertNotFound();
    }

    public function test_any_site_may_frame_a_tool_and_it_sets_no_cookies(): void
    {
        $this->withEmbed();

        foreach (['tuner', 'metronome', 'drone', 'pitch-pipe'] as $tool) {
            $embed = $this->get('/embed/tools/'.$tool)->assertOk();
            $this->assertSame(['*'], $this->csp($embed)['frame-ancestors'], $tool);
            $this->assertFalse($embed->headers->has('X-Frame-Options'), $tool);
            $this->assertSame([], $embed->headers->getCookies(), $tool);
            $this->assertFalse($embed->headers->has('Set-Cookie'), $tool);
            // Scripts only from us, nothing from anyone else (and GamePreload's inline script, by its hash).
            $this->assertSame(["'self'", GamePreload::hash()], $this->csp($embed)['script-src'], $tool);
        }

        $tuner = $this->get('/embed/tools/tuner');
        $this->assertStringStartsWith('microphone=(self)', (string) $tuner->headers->get('Permissions-Policy'));
        $this->assertStringStartsWith('microphone=()', (string) $this->get('/embed/tools/metronome')->headers->get('Permissions-Policy'));

        preg_match('#<pre id="embed">(.*)</pre>#s', (string) $tuner->getContent(), $m);
        $embed = (array) json_decode(html_entity_decode($m[1] ?? '{}', ENT_QUOTES), true);
        $this->assertSame(['tuner', 'Tuner', 'embed-', true], [$embed['tool'], $embed['name'], $embed['refPrefix'], $embed['listens']]);
        $this->assertStringEndsWith('/tools/tuner', $embed['pageUrl']);
    }

    public function test_our_own_pages_may_frame_our_embeds_and_nothing_else_frames_at_all(): void
    {
        // A page of ours (the embed picker's live preview) may frame our own embeds.
        $this->assertContains("'self'", $this->csp($this->get('/pricing'))['frame-src']);
        // The admin and the embeds themselves frame nothing.
        $this->assertSame(["'none'"], $this->csp($this->get('/embed/h/nothing'))['frame-src'] ?? ["'none'"]);
    }

    public function test_nothing_else_may_be_framed_by_another_site(): void
    {
        $this->assertSame(["'self'"], $this->csp($this->get('/pricing'))['frame-ancestors']);
        $this->get('/pricing')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertNotContains('*', $this->csp($this->get('/embed/h/nothing'))['frame-ancestors'] ?? []);
    }

    public function test_the_embeds_are_the_public_sites(): void
    {
        $this->withEmbed();
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://site.stackapella.test']);

        $this->get('https://site.stackapella.test/embed/tools/drone')->assertOk();
        $this->get('https://app.stackapella.test/embed/tools/drone')->assertRedirect('https://site.stackapella.test/embed/tools/drone');
    }
}
