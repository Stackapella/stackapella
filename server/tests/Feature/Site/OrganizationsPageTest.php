<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\OutreachTarget;
use App\Support\Outreach\Outreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The partner offer for organizations (Hillel, 2026-11-02: "Yes, build it"): its own page with a plain
 * email to Hillel, reached from the directors' and teachers' pages and, for someone who came by an
 * organization's outreach link, one quiet line on whatever page they land on. A singer never meets it.
 */
final class OrganizationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_states_the_offer_and_writes_to_hillel(): void
    {
        config(['site.partners_email' => 'partners@example.com']);

        $page = (string) $this->get('/for/organizations')->assertOk()->getContent();

        $this->assertStringContainsString(e(__('site.for.pages.organizations.headline')), $page);
        foreach ((array) __('site.for.pages.organizations.sections') as $section) {
            $this->assertStringContainsString(e($section['heading']), $page);
        }
        // A plain email, no form: the one setting, with its subject.
        $this->assertStringContainsString('href="mailto:partners@example.com?subject=Partner%20offer"', $page);
        $this->assertStringNotContainsString('<form', $page);
        // No Start singing in place of writing.
        $this->assertStringNotContainsString(e(__('pages.site.start_note')), $page);
    }

    public function test_a_singer_never_meets_it_but_directors_and_teachers_can(): void
    {
        $this->assertStringNotContainsString('/for/organizations', (string) $this->get('/')->assertOk()->getContent());
        $this->assertStringNotContainsString('/for/organizations', (string) $this->get('/for/beginners')->assertOk()->getContent());

        foreach (['choir-directors', 'music-teachers'] as $who) {
            $this->assertStringContainsString('href="/for/organizations"', (string) $this->get('/for/'.$who)->assertOk()->getContent(), $who);
        }
    }

    public function test_an_outreach_arrival_sees_one_line_to_the_offer_keeping_its_tag(): void
    {
        OutreachTarget::factory()->create(['ref' => 'o-test-choir']);
        Cache::forget(Outreach::REFS_CACHE);
        $line = e(__('pages.site.partner_link'));

        foreach (['/courses/barbershop', '/for/music-teachers', '/features/choirs', '/'] as $path) {
            $html = (string) $this->get($path.'?ref=o-test-choir')->assertOk()->getContent();
            $this->assertStringContainsString('href="/for/organizations?ref=o-test-choir"', $html, $path);
            $this->assertStringContainsString($line, $html, $path);
        }

        // Anyone else: an unknown tag, a campaign's tag, or none.
        foreach (['?ref=o-nobody', '?ref=bhs', ''] as $query) {
            $this->assertStringNotContainsString($line, (string) $this->get('/courses/barbershop'.$query)->assertOk()->getContent(), $query);
        }

        // Not on the offer's own page.
        $this->assertStringNotContainsString($line, (string) $this->get('/for/organizations?ref=o-test-choir')->assertOk()->getContent());
    }
}
