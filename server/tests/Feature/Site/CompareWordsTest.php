<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The comparison pages' words keep the plan's rules (docs/compare-pages-plan.md): every page has the
 * same rows, every claim about another product names its source or says "Not stated", the sources
 * are the product's own pages, no word reads as a dig, and each page shows its date and the names
 * line. Their being unlisted is ComparePagesTest's.
 */
final class CompareWordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_comparison_page_keeps_the_plans_rules(): void
    {
        $common = (array) Lang::get('site.compare.common');
        $pages = (array) Lang::get('site.compare.pages');
        $this->assertNotEmpty($pages);

        foreach ($pages as $slug => $page) {
            $this->assertSame(array_keys((array) $common['rows']), array_keys((array) $page['cells']), "{$slug}: the same rows as every page");
            $sources = count((array) $page['sources']);

            foreach ((array) $page['cells'] as $row => $cells) {
                $this->assertCount(count((array) $page['columns']), (array) $cells, "{$slug}.{$row}: a cell for each product");
                foreach ((array) $cells as [$text, $from]) {
                    $from = (array) $from;
                    $this->assertTrue($from !== [] || str_starts_with((string) $text, 'Not stated'), "{$slug}.{$row}: a source, or Not stated");
                    foreach ($from as $n) {
                        $this->assertTrue($n >= 1 && $n <= $sources, "{$slug}.{$row}: source {$n} is listed");
                    }
                }
            }
            foreach ((array) $page['sources'] as [, $url]) {
                $this->assertStringStartsWith('https://', (string) $url, "{$slug}: sources are links");
            }

            $words = strtolower((string) json_encode($page));
            foreach (['best', 'unlike', 'outdated', 'dead', 'worse', 'superior', 'cheaper', 'only app'] as $dig) {
                $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($dig, '/').'\b/', $words, "{$slug}: no \"{$dig}\"");
            }

            $this->get('/compare/'.$slug)->assertOk()
                ->assertSee($page['headline'])
                ->assertSee($common['asof'])
                ->assertSee($common['names'])
                ->assertSee('"@type":"FAQPage"', false);
        }

        $this->get('/compare')->assertOk()->assertSee($common['asof']);
    }
}
