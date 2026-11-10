<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * A tool that reports a result (the range test's voice type) carries its next step in the page: the band's line and
 * link for that result, the app address with the tool's ?ref= tag, and a part for every result the tool can give.
 * The tools_result flow checks the band changes in a browser.
 */
final class ToolResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_range_test_has_a_part_for_every_voice_type_it_can_name(): void
    {
        $result = (array) Lang::get('site.tools.pages.vocal-range-test.result');
        $card = (array) Lang::get('site.tools.pages.vocal-range-test.card');

        foreach (['bass', 'baritone', 'tenor', 'alto', 'mezzo', 'soprano'] as $type) {
            $this->assertArrayHasKey($type, (array) $result['parts'], "{$type}: a part to learn");
            $this->assertArrayHasKey($type, $card, "{$type}: the type's own word for the line");
        }
        $this->assertStringContainsString(':type', (string) $result['line']);
        $this->assertStringContainsString(':part', (string) $result['line']);
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
        $this->assertStringContainsString('song=amazing-grace', (string) $data['to']);
        $this->assertStringContainsString('ref=tool-vocal-range-test', (string) $data['to']);
        $this->assertSame('tenor', $data['parts']['tenor']);
        $this->assertSame('mezzo-soprano', $data['types']['mezzo']);
        // A tool without one has none.
        $this->assertStringNotContainsString('data-tool-result', (string) $this->get('/tools/tuner')->getContent());
    }
}
