<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The integrations (IntegrationPagesController): the hub and a page for Event Schedule and Invoice Ninja, each
 * linking the other app (Event Schedule's to its own page about Stackapella, so the two link each other), in the
 * sitemap and the footer. Neither connection is open, so each page says Coming and never offers to connect.
 */
final class IntegrationPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_the_hub_lists_each_integration_and_says_its_connection_is_coming(): void
    {
        $page = $this->get('/integrations')->assertOk()->assertSee(__('site.integrations.index.headline'));

        foreach (SitePages::INTEGRATIONS as $slug) {
            $page->assertSee('href="/integrations/'.$slug.'"', false)->assertSee(__('site.integrations.pages.'.$slug.'.name'));
        }
        $page->assertSee(__('site.integrations.coming'))->assertSee('href="/about"', false);
    }

    public function test_each_page_links_the_other_app_and_says_the_connection_is_coming(): void
    {
        $this->get('/integrations/event-schedule')->assertOk()
            ->assertSee('href="https://eventschedule.com"', false)
            ->assertSee('href="https://eventschedule.com/stackapella"', false)
            ->assertSee(__('site.integrations.pages.event-schedule.coming_title'))
            ->assertSee('href="/integrations/invoice-ninja"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->get('/integrations/invoice-ninja')->assertOk()
            ->assertSee('href="https://invoiceninja.com"', false)
            ->assertSee(__('site.integrations.pages.invoice-ninja.coming_title'))
            ->assertSee('href="/integrations/event-schedule"', false);

        $this->get('/integrations/zapier')->assertNotFound();
    }

    public function test_nothing_on_them_says_a_connection_works_today(): void
    {
        foreach (SitePages::INTEGRATIONS as $slug) {
            $words = (array) __('site.integrations.pages.'.$slug);

            // What works today is each app on its own, so neither side's list names the other: what they do
            // together lives under `coming` only.
            $this->assertStringNotContainsString('Stackapella', implode(' ', (array) $words['them']), $slug);
            $this->assertStringNotContainsString((string) $words['name'], implode(' ', (array) $words['us']), $slug);
            $this->assertStringContainsString('coming', strtolower((string) $words['status']), $slug);
        }
    }

    public function test_they_are_in_the_sitemap_and_the_footer(): void
    {
        $sitemap = (string) $this->get('/sitemap.xml')->getContent();
        foreach (['/integrations', ...array_map(static fn (string $s): string => '/integrations/'.$s, SitePages::INTEGRATIONS)] as $path) {
            $this->assertStringContainsString($path.'<', $sitemap, $path);
        }

        $this->get('/')->assertOk()->assertSee('href="/integrations"', false);
        app(Flags::class)->set('open_source', 'on', null, 'Open source');
        $this->get('/about')->assertOk()->assertSee('href="/integrations"', false);
    }
}
