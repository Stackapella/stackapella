<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\SocialPost;
use App\Models\Template;
use App\Services\Social\SocialPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Our marketing site answers only on stackapella.com (config('app.is_nexus')): an install of the
 * app under the AAL serves the app, its shared harmonies, help and legal pages, and none of ours.
 */
final class NexusTest extends TestCase
{
    use RefreshDatabase;

    public function test_off_the_nexus_the_marketing_pages_are_gone_and_slash_is_the_app(): void
    {
        config(['app.is_nexus' => false]);

        foreach (['/pricing', '/features', '/features/sing', '/about', '/press', '/whats-new', '/for/choirs', '/guides', '/guides/harmony', '/songs', '/songs/amazing-grace', '/open-source', '/selfhost', '/self-host', '/sitemap.xml'] as $path) {
            $this->get($path)->assertNotFound();
        }

        // A first-time visitor at / gets the app, not the landing.
        $this->assertShell($this->get('/')->assertOk()->getContent());
        $this->assertShell($this->get('/?landing')->getContent());

        // What the app needs stays: help, legal, security.txt.
        $this->get('/help')->assertOk();
        $this->get('/legal/terms')->assertOk();
        $this->get('/.well-known/security.txt')->assertOk();
    }

    public function test_on_the_nexus_they_are_there(): void
    {
        config(['app.is_nexus' => true]);

        $this->get('/pricing')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/')->assertOk()->assertViewIs('pages.landing');
    }

    public function test_off_the_nexus_no_social_post_is_proposed(): void
    {
        config(['app.is_nexus' => false, 'services.bluesky' => ['handle' => 'stackapella.com', 'app_password' => 'pw', 'service' => 'https://bsky.social']]);
        $this->assertSame(0, app(SocialPoster::class)->propose(Template::factory()->create(), SocialPost::NEW_SONG));
        $this->assertSame(0, SocialPost::query()->count());
    }
}
