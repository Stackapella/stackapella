<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A trail at the top of every page with a parent (Hillel, 2026-11-08: "show breadcrumbs at the top of pages so it's easy
 * to navigate back (where it makes sense) ie. at the top of each tool"): the steps up as links, the page itself last and
 * not a link, and the same trail for search where the page's own data has none.
 */
final class CrumbsTest extends TestCase
{
    use RefreshDatabase;

    /** The trail nav's inner HTML: the first one on the page. */
    private function trail(string $html): string
    {
        preg_match('#<nav class="s-crumb[^"]*" aria-label="[^"]*">(.*?)</nav>#s', $html, $m);

        return $m[1] ?? '';
    }

    public function test_a_tool_has_its_trail_in_the_tool_bar_at_the_top(): void
    {
        $html = (string) $this->get('/tools/tuner')->assertOk()->getContent();
        $bar = substr($html, (int) strpos($html, 'data-tool-bar'));
        $this->assertLessThan(strpos($html, 'data-tool="tuner"'), strpos($html, 'data-tool-bar'), 'the bar is above the tool');

        $trail = $this->trail($bar);
        $this->assertStringContainsString('<a href="/tools"', $trail);
        $this->assertStringContainsString(e(__('pages.nav.tools')), $trail);
        $this->assertMatchesRegularExpression('#<span aria-current="page"\s*>'.preg_quote(e(__('site.tools.pages.tuner.name')), '#').'</span>#', $trail);
        $this->assertSame(1, substr_count($html, 'class="s-crumb'), 'one trail, at the top');
    }

    public function test_the_reference_and_warm_up_pages_carry_their_trail_in_the_tool_bar(): void
    {
        $html = (string) $this->get('/intervals/major-third')->assertOk()->getContent();
        $this->assertStringContainsString('<a href="/intervals"', $this->trail(substr($html, (int) strpos($html, 'data-tool-bar'))));
        $this->assertSame(1, substr_count($html, 'class="s-crumb'));
    }

    public function test_help_guides_and_for_pages_lead_back_up_and_tell_search(): void
    {
        foreach (['/help/settings' => '/help', '/guides/breathing-basics' => '/guides', '/for/choir-directors' => '/features#who-h'] as $path => $up) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('<a href="'.$up.'"', $this->trail($html), $path);
            $this->assertStringContainsString('aria-current="page"', $this->trail($html), $path);
            $this->assertStringContainsString('"@type":"BreadcrumbList"', $html, $path);
        }
    }

    public function test_the_landing_and_the_top_level_pages_have_no_trail(): void
    {
        foreach (['/', '/pricing', '/tools', '/courses', '/guides', '/help'] as $path) {
            $this->assertStringNotContainsString('class="s-crumb', (string) $this->get($path)->assertOk()->getContent(), $path);
        }
    }
}
