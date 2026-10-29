<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * Off the nexus (a self-hosted install, IS_NEXUS off) the marketing pages aren't there
 * (docs/branding-matrix.md), so the pages that stay never link to them.
 */
final class NexusLinksTest extends TestCase
{
    private const MARKETING = ['/pricing', '/features', '/whats-new', '/guides', '/about', '/press', '/open-source', '/selfhost', '/how-it-works'];

    public function test_the_help_and_legal_pages_link_only_to_pages_that_are_there_off_the_nexus(): void
    {
        config(['app.is_nexus' => false]);

        foreach (['/help', '/legal/terms'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();
            foreach (self::MARKETING as $path) {
                $this->assertStringNotContainsString('href="'.$path.'"', $html, $page.' links to '.$path);
            }
            $this->assertStringContainsString('href="/help"', $html, $page.' keeps Help');
            $this->assertStringContainsString('href="/legal/privacy"', $html, $page.' keeps the legal pages');
        }
    }

    public function test_on_the_nexus_the_footer_keeps_every_page(): void
    {
        config(['app.is_nexus' => true]);

        $html = (string) $this->get('/help')->assertOk()->getContent();

        foreach (['/pricing', '/features', '/about', '/press'] as $path) {
            $this->assertStringContainsString('href="'.$path.'"', $html, $path);
        }
    }
}
