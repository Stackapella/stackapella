<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every free tool page and the hub open with the Stackapella brand row (Hillel: "make sure it (and all tools) have our
 * logo and theme picker at the top"; docs/tools-plan.md, "Tool page frame"): before anything else on the page, the
 * mark and the name "Stackapella" linking to the landing, and the Theme button. The same row shows installed (standalone);
 * the tools_frame flow checks that in a browser.
 */
final class ToolFrameTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_listening_tools_may_ask_for_the_mic(): void
    {
        // The hub and every live tool that doesn't listen say microphone=() (config tools.listening has the ones that do).
        $quiet = array_values(array_diff(SitePages::TOOLS, (array) config('tools.listening')));
        foreach (['/tools', ...array_map(static fn (string $tool): string => '/tools/'.$tool, $quiet)] as $path) {
            $this->assertStringStartsWith('microphone=()', (string) $this->get($path)->headers->get('Permissions-Policy'), $path);
        }
        foreach (array_intersect(SitePages::TOOLS, (array) config('tools.listening')) as $tool) {
            $this->assertStringStartsWith('microphone=(self)', (string) $this->get('/tools/'.$tool)->headers->get('Permissions-Policy'), $tool);
        }
    }

    public function test_every_tools_page_opens_with_the_site_header_then_the_tool_bar(): void
    {
        if (SitePages::TOOLS === []) {
            $this->assertSame(404, $this->get('/tools')->status(), 'no hub until a tool is listed');

            return;
        }

        // The hub only once it's live; before, /tools leads to the first tool.
        if (! SitePages::hubLive()) {
            $this->get('/tools')->assertRedirect(SitePages::toolsHome());
        }

        foreach ([...(SitePages::hubLive() ? ['/tools'] : []), ...array_map(static fn (string $tool): string => '/tools/'.$tool, SitePages::TOOLS)] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $body = substr($html, (int) strpos($html, '<body'));
            // The first element in the body is the site's own header (Hillel, 2026-11-08: "when using a tool make sure we
            // show the header"), with its nav.
            $this->assertMatchesRegularExpression('/^<body[^>]*>\s*<header class="s-wrap s-top"/', $body, "{$path}: the header comes first");
            $top = substr($body, (int) strpos($body, '<header class="s-wrap s-top"'));
            $top = substr($top, 0, (int) strpos($top, '</header>'));
            // The mark and name lead to the landing, counted (Hillel: "if a user clicks the logo at the top of a tool it
            // should take them to the main landing page").
            $ref = $path === '/tools' ? 'tools' : 'tool-'.substr($path, strlen('/tools/'));
            $this->assertStringContainsString('href="/?ref='.$ref.'" class="s-brand"', $top, "{$path}: the logo links to the landing");
            $this->assertStringContainsString('<svg', $top, "{$path}: the mark");
            $this->assertStringContainsString('>Stackapella</span>', $top, "{$path}: the name");
            $this->assertStringContainsString('href="/courses"', $top, "{$path}: the nav");
            // Light and dark is the header's switch, where every page has it (Hillel, 2026-11-13); the tool bar has none.
            $this->assertStringContainsString('data-theme-toggle', $top, "{$path}: light and dark in the header");
            $bar = substr($body, (int) strpos($body, '<div class="s-wrap s-tbrand'));
            $bar = substr($bar, 0, (int) strpos($bar, '</div>', (int) strpos($bar, 's-tbrand-end')) + 6);
            $this->assertStringNotContainsString('data-theme-menu-button', $bar, "{$path}: no theme button in the tool bar");
            $this->assertStringNotContainsString('data-theme-toggle', $bar, "{$path}: no theme button in the tool bar");
        }
    }
}
