<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Admin\StaffDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cloudflare's edge keeps only what reads the same for everyone (SongPagesController::edgeHeaders): a page that a later
 * step makes private, as a language preview on an admin's device is, never carries the edge's header.
 */
final class EdgeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_language_preview_of_an_edge_page_is_never_kept_at_the_edge(): void
    {
        $this->assertNotNull($this->get('/songs')->assertOk()->headers->get('Cloudflare-CDN-Cache-Control'), 'an everyone page says the edge may keep it');

        $this->app->instance('env', 'production');
        $value = StaffDevice::previewCookie(request())->getValue();
        $preview = $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, $value)->get('/es/songs')->assertOk();

        $this->assertStringContainsString('Preview: ES is off for everyone else', (string) $preview->getContent());
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));
        $this->assertSame('no-store', $preview->headers->get('Cloudflare-CDN-Cache-Control'), "one admin's preview never kept for everyone");
        // And a page that already tells the edge no-store keeps saying so (the app's shell).
        $this->assertSame('no-store', $this->get('/sing')->headers->get('Cloudflare-CDN-Cache-Control'));
    }
}
