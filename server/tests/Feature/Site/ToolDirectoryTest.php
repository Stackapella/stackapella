<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Below every tool (docs/tools/below-the-fold.md): About Stackapella with the tool's own headline, then More free tools,
 * the next tool this one points to and every other live tool in its group. Only live tools, never this one, never the
 * featured one twice, and "See all :count tools" counts the live ones.
 */
final class ToolDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_live_tool_has_its_headline_tag_and_group(): void
    {
        $groups = array_keys((array) Lang::get('site.tools.index.groups'));
        $this->assertSame(array_keys(SitePages::TOOL_GROUPS), $groups, 'every group has a name, in the same order');

        foreach (SitePages::TOOLS as $tool) {
            $page = SitePages::tool($tool);
            $this->assertIsString($page['tie'] ?? null, "{$tool}: its About headline");
            $this->assertLessThanOrEqual(60, mb_strlen((string) $page['tie']), "{$tool}: the headline fits");
            $this->assertIsString($page['tag'] ?? null, "{$tool}: its directory tag");
            $this->assertLessThanOrEqual(36, mb_strlen((string) $page['tag']), "{$tool}: the tag fits");
            $this->assertContains($page['group'] ?? null, $groups, "{$tool}: a known group");
            $this->assertArrayNotHasKey('try', $page, "{$tool}: the headline and button say it now");
        }
    }

    public function test_the_directory_lists_only_the_other_live_tools_once_each(): void
    {
        foreach (SitePages::TOOLS as $tool) {
            $directory = SitePages::directory($tool);
            $listed = array_merge(...array_map(static fn (array $group): array => array_column($group['tools'], 'slug'), $directory['groups']));
            $featured = $directory['featured']['slug'] ?? null;

            $this->assertNotContains($tool, $listed, "{$tool}: not itself");
            $this->assertSame($listed, array_values(array_unique($listed)), "{$tool}: each once");
            if ($featured !== null) {
                $this->assertNotContains($featured, $listed, "{$tool}: the featured one isn't listed again");
                $this->assertSame(SitePages::tool($tool)['related'][1], $featured, "{$tool}: the featured one is its related tool");
            }
            $this->assertEqualsCanonicalizing(array_values(array_diff(SitePages::TOOLS, [$tool])), [...$listed, ...($featured !== null ? [$featured] : [])], "{$tool}: every other live tool");
            $this->assertSame(count(SitePages::TOOLS), $directory['count']);
            // The groups keep their order and none is empty.
            $keys = array_column($directory['groups'], 'key');
            $this->assertSame(array_values(array_intersect(array_keys(SitePages::TOOL_GROUPS), $keys)), $keys);
        }
    }

    public function test_a_tool_page_shows_its_headline_and_the_other_tools(): void
    {
        if (! SitePages::hubLive()) {
            $this->markTestSkipped('the directory shows once the hub is live');
        }
        $tool = SitePages::TOOLS[0];
        $html = (string) $this->get('/tools/'.$tool)->assertOk()->getContent();

        $this->assertStringContainsString('data-try-line>'.e(SitePages::tool($tool)['tie']).'</h2>', $html);
        $this->assertStringContainsString(e(str_replace(':count', (string) count(SitePages::TOOLS), (string) Lang::get('site.tools.index.see_all'))), $html);
        foreach (array_diff(SitePages::TOOLS, [$tool]) as $other) {
            $this->assertStringContainsString('href="/tools/'.$other.'"', $html, "{$tool}: links {$other}");
        }
        // A planned tool that isn't live is never linked (any of config('tools.slugs') not yet listed, nor a live variant).
        foreach (array_diff((array) config('tools.slugs'), SitePages::TOOLS, SitePages::variants()) as $planned) {
            $this->assertStringNotContainsString('href="/tools/'.$planned.'"', $html, "{$tool}: never links the unlisted {$planned}");
        }

        // The hub has the About panel with its own headline and no directory (its cards are the list).
        $hub = (string) $this->get('/tools')->assertOk()->getContent();
        $this->assertStringContainsString('data-try-line>'.e((string) Lang::get('site.tools.index.tie')).'</h2>', $hub);
        $this->assertStringNotContainsString('class="s-wrap s-sec s-tdir"', $hub);
    }

    public function test_a_tool_names_its_variants_under_its_how_to_and_a_variant_its_siblings(): void
    {
        $also = static function (string $html): array {
            preg_match('/<p class="s-tool-related s-tool-also">(.*?)<\/p>/s', $html, $line);
            preg_match_all('/<a href="\/tools\/([a-z0-9-]+)" class="s-lk">/', $line[1] ?? '', $links);

            return $links[1];
        };

        $tuner = (string) $this->get('/tools/tuner')->assertOk()->getContent();
        $this->assertSame(['guitar-tuner', 'bass-tuner', 'ukulele-tuner', 'violin-tuner', 'banjo-tuner', 'mandolin-tuner', 'cello-tuner', 'viola-tuner', 'voice-tuner'], $also($tuner));
        $this->assertStringContainsString('>'.e((string) __('site.tools.variants.violin-tuner.name')).'</a>', $tuner);
        $this->assertSame(['tanpura'], $also((string) $this->get('/tools/drone')->assertOk()->getContent()));

        $this->assertSame(['guitar-tuner', 'bass-tuner', 'violin-tuner', 'banjo-tuner', 'mandolin-tuner', 'cello-tuner', 'viola-tuner', 'voice-tuner'], $also((string) $this->get('/tools/ukulele-tuner')->assertOk()->getContent()), 'never itself');
        $this->assertSame([], $also((string) $this->get('/tools/tanpura')->assertOk()->getContent()));
        $this->assertStringNotContainsString('s-tool-also', (string) $this->get('/tools/metronome')->assertOk()->getContent());
    }
}
