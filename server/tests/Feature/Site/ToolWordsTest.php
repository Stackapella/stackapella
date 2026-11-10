<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * A free tool's module names the words it shows (`export const WORDS = [...]` in js/public/tools/<tool>.js); the page
 * passes them from lang (site.tools.pages.<tool>.card). They must match exactly: a word the module wants and the page
 * lacks would show nothing, and one the page has that the module doesn't would be dead.
 */
final class ToolWordsTest extends TestCase
{
    public function test_each_tools_words_are_exactly_the_ones_its_module_shows(): void
    {
        $checked = 0;

        foreach (array_keys(SitePages::TOOL_MOUNTS) as $tool) {
            // A tool that's listed, or whose page words are written: its module may be on main before its page is.
            if (! in_array($tool, SitePages::TOOLS, true) && ! Lang::has('site.tools.pages.'.$tool.'.card')) {
                continue;
            }
            $file = dirname(base_path()).'/web'.(SitePages::TOOL_MODULES[$tool] ?? '/js/public/tools/'.$tool.'.js');
            // WORDS, and any other exported word list (the mic test's FIX_WORDS, its browser steps).
            if (! is_file($file) || preg_match_all('/export const [A-Z_]*WORDS = \[(.*?)\];/s', (string) file_get_contents($file), $match) < 1) {
                continue;
            }
            preg_match_all("/'(\w+)'/", implode(',', $match[1]), $keys);
            $card = array_keys((array) Lang::get('site.tools.pages.'.$tool.'.card'));
            sort($card);
            $wanted = array_values(array_unique($keys[1])); // a word two lists share is one word
            sort($wanted);
            $this->assertSame($wanted, $card, "{$tool}: lang site.tools.pages.{$tool}.card and its module's WORDS");
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'no tool module with WORDS was found');
    }
}
