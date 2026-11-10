<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tool's way to the reference pages about the same things (/intervals, /chords, /voice-types): one quiet line after
 * its guide (its words' `reference`), and a guide table's rows each linked to their own page (the table's `links`).
 * Every link opens a live page, and each row's link is its own.
 */
final class ToolReferenceLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_tools_reference_links_open_live_pages(): void
    {
        $checked = 0;
        foreach (SitePages::TOOLS as $tool) {
            $page = SitePages::tool($tool);
            $hrefs = [];
            if (is_array($page['reference'] ?? null)) {
                $hrefs[] = (string) $page['reference'][1];
            }
            $html = '';
            foreach ((array) ($page['guide'] ?? []) as $section) {
                $links = (array) ($section['table']['links'] ?? []);
                if ($links === []) {
                    continue;
                }
                $rows = (array) $section['table']['rows'];
                $this->assertCount(count($rows), $links, "{$tool}: a link for every row");
                $html = $html !== '' ? $html : (string) $this->get('/tools/'.$tool)->assertOk()->getContent();
                foreach ($rows as $i => $row) {
                    $this->assertStringContainsString('<th scope="row"><a href="'.$links[$i].'" class="s-lk">'.e((string) $row[0]).'</a></th>', $html, "{$tool}: row {$i}");
                }
                $hrefs = [...$hrefs, ...$links];
            }
            if (is_array($page['reference'] ?? null)) {
                $html = $html !== '' ? $html : (string) $this->get('/tools/'.$tool)->assertOk()->getContent();
                $this->assertStringContainsString('<a href="'.$page['reference'][1].'" class="s-lk">'.e((string) $page['reference'][0]), $html, $tool);
            }
            foreach (array_unique($hrefs) as $href) {
                $this->get($href)->assertOk();
                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(30, $checked, 'the interval, chord and voice-type tools link their pages');
    }
}
