<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * A tool that reports a result (the range test's range and voice type) carries its next step in the page: the band's
 * line and link for that result, the app address with the tool's ?ref= tag (page.js adds ?range=, the range in letter
 * names: docs/retention-plan.md, idea 7), and the type's own word for every result the tool can give. The tools_result
 * flow checks the band changes in a browser.
 */
final class ToolResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_range_tests_line_says_the_range_and_names_every_voice_type_it_can_give(): void
    {
        $result = (array) Lang::get('site.tools.pages.vocal-range-test.result');
        $card = (array) Lang::get('site.tools.pages.vocal-range-test.card');

        foreach (['bass', 'baritone', 'tenor', 'alto', 'mezzo', 'soprano'] as $type) {
            $this->assertArrayHasKey($type, $card, "{$type}: the type's own word for the line");
        }
        foreach ([':low', ':high', ':type'] as $placeholder) {
            $this->assertStringContainsString($placeholder, (string) $result['line']);
        }
        $this->assertSame('/', $result['to'], 'the app opens on Home, which fits its picks to the range');
    }

    public function test_a_listed_tool_with_a_result_carries_it_in_the_page(): void
    {
        if (! in_array('vocal-range-test', SitePages::TOOLS, true)) {
            $this->markTestSkipped('the range test is not listed yet');
        }

        $html = (string) $this->get('/tools/vocal-range-test')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<script type="application\/json" data-tool-result>(.*?)<\/script>/s', $html, $match));
        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertStringNotContainsString('song=', (string) $data['to']);
        $this->assertStringContainsString('ref=tool-vocal-range-test', (string) $data['to']);
        $this->assertSame([], $data['parts']);
        $this->assertSame('mezzo-soprano', $data['types']['mezzo']);
        // A tool without one has none.
        $this->assertStringNotContainsString('data-tool-result', (string) $this->get('/tools/tuner')->getContent());
    }
}
