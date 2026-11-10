<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Landing;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The courses and the free tools a click from every page (Hillel, 2026-11-07: "make the tools and courses pages
 * more reachable from the main page, maybe also in the footer"): in the top bar, on the landing after how it works,
 * and in the footer's Learn and Reference columns, every link a page that opens.
 */
final class SiteNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_top_bar_and_the_footer_lead_to_the_courses_and_the_tools(): void
    {
        $tools = SitePages::toolsHome();

        foreach (['/', '/pricing', '/courses', '/features'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<nav class="s-nav"[^>]*>.*href="/courses".*href="'.preg_quote($tools, '#').'"#s', $html, $path.': the top bar');
            $this->assertMatchesRegularExpression('#<nav class="s-menu-panel"[^>]*>.*href="/courses".*href="'.preg_quote($tools, '#').'"#s', $html, $path.': the phone menu');
            $this->assertStringContainsString(e(__('pages.nav.groups.learn')), $html);
            $this->assertStringContainsString(e(__('pages.nav.groups.reference')), $html);
        }
        // The tools, reference and warm-up pages keep their own brand row (docs/tools-plan.md, "Tool page frame"), and
        // share the footer.
        foreach (['/tools/tuner', '/intervals', '/warmups'] as $path) {
            $this->assertStringContainsString(e(__('pages.nav.groups.learn')), (string) $this->get($path)->assertOk()->getContent(), $path);
        }

        foreach (['/courses', $tools, '/warmups', '/guides', '/intervals', '/chords', '/voice-types', '/tools/note-frequency-chart', '/for/choir-directors', '/how-it-works'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_every_footer_column_has_as_many_links(): void
    {
        app(Flags::class)->set('open_source', 'on', null, 'Open source');
        $html = (string) $this->get('/')->assertOk()->getContent();
        preg_match('#<nav class="s-foot-cols".*?</nav>#s', $html, $foot);
        preg_match_all('#<ul>(.*?)</ul>#s', $foot[0], $columns);

        $this->assertCount(6, $columns[1]);
        $this->assertSame([4], array_values(array_unique(array_map(static fn (string $col): int => substr_count($col, '<li>'), $columns[1]))));
    }

    public function test_the_landing_shows_free_courses_and_free_tools_after_how_it_works(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $how = strpos($html, 'id="steps-h"');
        $courses = strpos($html, 'data-landing-courses');
        $tools = strpos($html, 'data-landing-tools');
        $this->assertNotFalse($how);
        $this->assertNotFalse($courses);
        $this->assertNotFalse($tools);
        $this->assertGreaterThan($how, $courses);
        $this->assertGreaterThan($courses, $tools);

        $this->assertStringContainsString('href="/features/course"', $html);
        foreach (Landing::FEATURED_TOOLS as $slug) {
            $this->assertStringContainsString('href="/tools/'.$slug.'"', $html);
        }
    }
}
