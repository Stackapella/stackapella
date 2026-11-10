<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The tool variants (docs/seo-growth-plan.md item 3, SitePages::TOOL_VARIANTS): each is its tool's page opened with
 * a preset for one search, with its own words, its own address in the counts, manifest and mic policy, its tool
 * featured below it, and its own place in the sitemap, but never on the hub's list.
 */
final class ToolVariantPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_variant_is_live_and_known_to_the_counts_manifests_and_mic_policy(): void
    {
        $this->assertSame(array_keys(SitePages::TOOL_VARIANTS), SitePages::variants(), 'every variant has its words and a live tool');

        foreach (SitePages::TOOL_VARIANTS as $slug => $variant) {
            $this->assertContains($slug, (array) config('tools.slugs'), "{$slug} counts and installs");
            $this->assertNotContains($slug, SitePages::TOOLS, "{$slug} stays off the hub's list");
            $this->assertSame(
                in_array($variant['tool'], (array) config('tools.listening'), true),
                in_array($slug, (array) config('tools.listening'), true),
                "{$slug} may use the mic exactly when {$variant['tool']} does",
            );
        }
    }

    public function test_each_variant_has_its_own_words_its_preset_and_its_tool(): void
    {
        foreach (SitePages::TOOL_VARIANTS as $slug => $variant) {
            $words = (array) Lang::get('site.tools.variants.'.$slug);
            $this->assertSame((string) $words['title'].' | '.__('meta.brand'), SitePages::title((string) $words['title']), "{$slug}'s title is too long to carry the name in search");
            $this->assertLessThanOrEqual(155, mb_strlen((string) $words['description']), "{$slug}'s description");
            $html = (string) $this->get('/tools/'.$slug)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$slug} has one h1");
            $this->assertStringContainsString('>'.e((string) $words['headline']).'</h1>', $html);
            foreach ([...(array) $words['how'], ...array_merge(...(array) $words['faq'])] as $line) {
                $this->assertStringContainsString(e((string) $line), $html, $slug);
            }
            $this->assertStringContainsString('data-tool="'.$slug.'"', $html);
            $this->assertStringContainsString('data-mount="'.SitePages::TOOL_MOUNTS[$variant['tool']].'"', $html);
            $this->assertStringContainsString('href="/tools/'.$slug.'/manifest.webmanifest"', $html);
            $this->assertStringContainsString('<link rel="canonical" href="'.e(Hosts::publicUrl().'/tools/'.$slug).'">', $html);

            // The way into the app is its tool's, counted as the variant's.
            $to = (string) __('site.tools.pages.'.$variant['tool'].'.try_to');
            $this->assertStringContainsString('href="'.e(Hosts::app($to.(str_contains($to, '?') ? '&' : '?').'ref=tool-'.$slug)).'"', $html);

            // The preset, for the visit, in the page's tool data.
            preg_match('/<script type="application\/json" data-tool-data>(.*?)<\/script>/s', $html, $data);
            $this->assertSame($variant['preset'], json_decode(html_entity_decode($data[1] ?? ''), true)['preset'] ?? null, $slug);

            // Its tool is the featured next tool below it.
            $this->assertStringContainsString('href="/tools/'.$variant['tool'].'" class="s-tfeat', $html, "{$slug} features {$variant['tool']}");
        }
    }

    public function test_variants_are_in_the_sitemap_but_not_on_the_hub(): void
    {
        $paths = app(SitemapPaths::class)->all();
        $hub = (string) $this->get('/tools')->assertOk()->getContent();

        foreach (array_keys(SitePages::TOOL_VARIANTS) as $slug) {
            $this->assertContains('/tools/'.$slug, $paths);
            $this->assertStringNotContainsString('href="/tools/'.$slug.'"', $hub);
        }
    }

    public function test_a_tool_names_its_own_variants(): void
    {
        $this->assertSame(['guitar-tuner', 'bass-tuner', 'ukulele-tuner', 'violin-tuner', 'banjo-tuner', 'mandolin-tuner', 'cello-tuner', 'viola-tuner', 'voice-tuner'], array_column(SitePages::variantsOf('tuner'), 'slug'));
        $this->assertSame([['slug' => 'tanpura', 'name' => (string) __('site.tools.variants.tanpura.name')]], SitePages::variantsOf('drone'));
        $this->assertSame([], SitePages::variantsOf('metronome'));
    }
}
