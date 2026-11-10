<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Http\Middleware\PreloadModules;
use App\Support\Site\ToolPreloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A public page's module scripts' import chains in its <head> (App\Http\Middleware\PreloadModules, from
 * web/js/public/tools/preloads.json): every module of each script's static graph once, before </head>, never a
 * link the page already had, and nothing added to a page or answer that has no module script.
 */
final class PreloadModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tool_page_names_site_js_and_page_js_chains_once_in_its_head(): void
    {
        $html = (string) $this->get('/tools/tuner')->assertOk()->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        foreach ([...ToolPreloads::for('/js/public/site.js'), ...ToolPreloads::for('/js/public/tools/page.js'), ...ToolPreloads::for('/js/public/tools/tuner.js')] as $module) {
            $this->assertSame(1, substr_count($html, '<link rel="modulepreload" href="'.$module.'"'), "{$module} preloaded once");
            $this->assertStringContainsString('href="'.$module.'"', $head, "{$module} in the head");
        }
        $this->assertContains('/js/public/game-toggle.js', ToolPreloads::for('/js/public/site.js'), "site.js's import is in its graph");
    }

    public function test_a_page_with_its_own_demo_script_gets_that_chain_too(): void
    {
        $graph = ToolPreloads::for('/js/public/counterpoint.js');
        $this->assertNotSame([], $graph, 'the counterpoint demo has a graph');

        $links = implode('', PreloadModules::links('<head></head><body><script type="module" src="/js/public/counterpoint.js"></script><script type="module" src="/js/public/site.js"></script></body>'));
        foreach ([...$graph, ...ToolPreloads::for('/js/public/site.js')] as $module) {
            $this->assertSame(1, substr_count($links, 'href="'.$module.'"'), $module);
        }
    }

    public function test_links_the_page_has_are_kept_and_never_doubled_and_unknown_scripts_add_nothing(): void
    {
        $have = '<head><link rel="modulepreload" href="/js/public/site.js"></head><script type="module" src="/js/public/site.js?v=abc"></script>';
        $lacks = array_values(array_diff(ToolPreloads::for('/js/public/site.js'), ['/js/public/site.js']));
        $this->assertSame(['/js/public/game-toggle.js', '/js/public/season.js'], $lacks, "site.js's imports");
        $this->assertSame(array_map(static fn (string $m): string => '<link rel="modulepreload" href="'.$m.'">'."\n", $lacks), PreloadModules::links($have), 'only what the page lacks, a ?v= read past');
        $this->assertSame([], PreloadModules::links('<script type="module" src="/js/nothing-here.js"></script>'));
        $this->assertSame([], PreloadModules::links('<script type="module" src="https://example.org/js/x.js"></script>'), 'only our own addresses');
    }

    public function test_an_answer_that_is_not_an_html_page_is_left_alone(): void
    {
        $json = $this->getJson('/api/auth/config')->assertOk();
        $this->assertStringNotContainsString('modulepreload', (string) $json->getContent());
    }
}
