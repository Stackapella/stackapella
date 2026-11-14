<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Counts\Funnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app's page carries the running experiments (Funnel::running, as auth.config's `experiments`), so a first visit
 * draws lesson 1's arm at once rather than a round trip later (frontend, the lesson 1 speed task); and the public pages
 * may prefetch Vue from the app's CDN (connect-src).
 */
final class AppShellExperimentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_page_names_the_running_experiments_as_auth_config_does(): void
    {
        config(['counts.enabled' => true, 'app.coming_soon' => false]);
        $html = (string) $this->get('/sing')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<meta name="experiments" content="([^"]*)">#', $html, $meta), 'the meta is there');
        $listed = json_decode(html_entity_decode($meta[1], ENT_QUOTES), true);

        $this->assertSame(app(Funnel::class)->running(), $listed);
        $this->assertSame($this->getJson('/api/auth/config')->json('data.experiments'), $listed, 'the same as auth.config');
    }

    public function test_a_public_page_may_prefetch_vue_from_the_cdn(): void
    {
        $csp = (string) $this->get('/songs')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertSame(1, preg_match('#connect-src ([^;]+)#', $csp, $connect));
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $connect[1]);
    }
}
