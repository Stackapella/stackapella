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

    public function test_the_top_bar_reads_songs_courses_tools_pricing_and_for_choirs_is_led_to_with_its_place(): void
    {
        // Hillel, 2026-11-12: "change the header options to songs courses tools pricing", For choirs led to elsewhere.
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        preg_match('#<nav class="s-nav"[^>]*>(.*?)</nav>#s', $html, $nav);
        preg_match_all('#href="([^"]+)"#', $nav[1] ?? '', $links);
        $this->assertSame(['/songs', '/courses', SitePages::toolsHome(), '/pricing'], $links[1]);
        $this->assertStringContainsString('aria-current="page" >'.e(__('pages.nav.pricing')).'</a>', str_replace('aria-current="page">', 'aria-current="page" >', $nav[1] ?? ''));
        // Open the app is the bar's one primary (Hillel's open source design).
        $this->assertMatchesRegularExpression('#class="btn-primary">'.preg_quote(e(__('pages.nav.open_app')), '#').'</a>#', $html);
        $this->assertStringContainsString('<a href="/for" class="s-flink">'.e(__('pages.nav.who_for')).'</a>', $html);

        // A director who came by a lead-in starts their group counted by that place.
        $this->get('/for/choir-directors?ref=for-choirs-songs')->assertOk()->assertSee('ref=for-choirs-songs', false);
        $this->get('/for/choir-directors?ref=someone-else')->assertOk()->assertSee('ref=for-choir-directors', false);
    }

    public function test_the_top_bar_and_the_footer_lead_to_the_courses_and_the_tools(): void
    {
        $tools = SitePages::toolsHome();

        // The home page has a top line of its own (pages/landing.blade.php) with the same ways on, and the site's footer.
        $home = (string) $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<nav class="lnf-nav"[^>]*>.*href="/courses".*href="'.preg_quote($tools, '#').'"#s', $home, 'the home page\'s top line');
        $this->assertStringContainsString(e(__('pages.nav.groups.learn')), $home);
        $this->assertStringContainsString(e(__('pages.nav.groups.reference')), $home);
        foreach (['/pricing', '/courses', '/features'] as $path) {
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

        // Six columns of four (Hillel, 2026-11-24: the audiences are one page, Who it's for, under Product).
        $this->assertCount(6, $columns[1]);
        $this->assertSame([4, 4, 4, 4, 4, 4], array_map(static fn (string $col): int => substr_count($col, '<li>'), $columns[1]));
        preg_match_all('#href="([^"]+)"#', $columns[1][2], $product);
        $this->assertSame(['/features', '/for', '/how-it-works', '/pricing'], $product[1]);
        $this->assertStringNotContainsString('href="/for/learners" class="s-flink"', $foot[0]);
    }

    public function test_the_landing_shows_free_courses_and_free_tools_after_how_it_works(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        // The four steps on the stage, then the scene that says what's free, each of its three a way in.
        $share = strpos($html, 'id="lnf-s4"');
        $free = strpos($html, 'id="lnf-s5"');
        $this->assertNotFalse($share);
        $this->assertNotFalse($free);
        $this->assertLessThan($free, $share);
        $scene = substr($html, (int) $free, (int) strpos($html, 'id="lnf-s7"') - (int) $free);
        $this->assertStringContainsString('href="/courses"', $scene);
        $this->assertStringContainsString('href="/songs"', $scene);
        $this->assertStringContainsString('href="'.SitePages::toolsHome().'"', $scene);
        $this->assertStringContainsString('/learn/1-comfortable', $scene);
    }
}
