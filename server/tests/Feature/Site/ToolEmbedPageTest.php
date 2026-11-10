<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Free tools on other sites (docs/seo-growth-plan.md, item 2): /embed/tools/<tool> is the bare tool with its words
 * and the credit, kept out of search; /tools/embed makes the one line to copy, for the tools that are live and
 * embeddable only.
 */
final class ToolEmbedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_embedded_tool_is_the_bare_tool_with_its_words_and_credit(): void
    {
        foreach (array_intersect((array) config('tools.embeddable'), SitePages::TOOLS) as $tool) {
            $html = (string) $this->get('/embed/tools/'.$tool)->assertOk()->getContent();

            $this->assertStringContainsString('<div data-embed-stage', $html, $tool);
            // The tool page's arcade screen, so the link's ?skin=game gives the frame game mode's whole look.
            $this->assertMatchesRegularExpression('/<html [^>]*data-embed data-gs>/', $html, $tool);
            $this->assertStringContainsString('<body class="s-embed s-arcade">', $html, $tool);
            $this->assertStringContainsString('<meta name="robots" content="noindex">', $html, "{$tool}: the tool's own page is the one to find");
            $this->assertSame(1, preg_match('/<script type="application\/json" data-embed-data>(.*?)<\/script>/s', $html, $match), $tool);
            $boot = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($boot);
            $this->assertSame($tool, $boot['tool']);
            $this->assertSame(SitePages::TOOL_MOUNTS[$tool], $boot['mount']);
            $this->assertSame((array) Lang::get('site.tools.pages.'.$tool.'.card'), $boot['words'], "{$tool}: its words from lang");
            $this->assertSame((string) Lang::get('site.tools.index.by'), $boot['credit']);
            // No brand row, no heading: just the tool.
            $this->assertStringNotContainsString('s-tbrand', $html);
            $this->assertStringNotContainsString('<h1', $html);
        }
    }

    public function test_the_embed_maker_offers_the_live_embeddable_tools(): void
    {
        $html = (string) $this->get('/tools/embed')->assertOk()->getContent();
        $live = array_values(array_intersect((array) config('tools.embeddable'), SitePages::TOOLS));

        $this->assertNotSame([], $live);
        foreach ($live as $tool) {
            $this->assertStringContainsString('<option value="'.$tool.'">', $html, $tool);
        }
        foreach (array_diff(SitePages::TOOLS, (array) config('tools.embeddable')) as $notEmbeddable) {
            $this->assertStringNotContainsString('<option value="'.$notEmbeddable.'">', $html, $notEmbeddable);
        }
        $this->assertStringContainsString('<h1 id="page-h">'.e((string) Lang::get('site.tools.embed.headline')).'</h1>', $html);
    }
}
