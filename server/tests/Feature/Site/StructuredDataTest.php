<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What search engines read: valid JSON-LD, prices only from config, and the page's own words.
 */
final class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_describes_the_organization_the_app_its_prices_and_its_questions(): void
    {
        $items = $this->jsonLd((string) $this->get('/', ['CF-IPCountry' => 'GB'])->getContent());
        $types = array_column($items, '@type');

        $this->assertSame(['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'], $types);
        $app = $items[2];
        $this->assertSame('GBP', $app['offers'][1]['priceCurrency']);
        $this->assertSame(number_format(config('billing.amounts.gbp.month') / 100, 2, '.', ''), $app['offers'][1]['price']);
        $this->assertCount(count((array) __('pages.landing.faq')), $items[3]['mainEntity']);
        $this->assertStringNotContainsString(config('site.founder'), json_encode($items));
    }

    public function test_a_song_page_describes_its_composition(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace']);
        $template->forceFill(['current_version_id' => TemplateVersion::factory()->create(['template_id' => $template->id])->id])->save();

        [$song] = $this->jsonLd((string) $this->get('/songs/amazing-grace')->getContent());

        $this->assertSame('MusicComposition', $song['@type']);
        $this->assertSame('Amazing Grace', $song['name']);
        $this->assertFalse($song['isAccessibleForFree']);
    }

    public function test_a_song_page_unfurls_with_its_title_and_a_card(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing <Grace>']);
        $template->forceFill(['current_version_id' => TemplateVersion::factory()->create(['template_id' => $template->id])->id])->save();

        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();

        // Every tag a link preview reads, the title escaped, the picture absolute and its size given.
        foreach (['og:site_name', 'og:title', 'og:description', 'og:url', 'og:image', 'og:image:width', 'og:image:height', 'og:image:alt'] as $property) {
            $this->assertStringContainsString('property="'.$property.'"', $html, $property);
        }
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertStringContainsString('Amazing &lt;Grace&gt;', $html);
        $this->assertStringNotContainsString('Amazing <Grace>', $html);
        preg_match('#property="og:image" content="([^"]+)"#', $html, $image);
        $this->assertStringStartsWith('http', $image[1] ?? '', 'the picture is an absolute address');
    }

    public function test_nothing_in_the_data_can_close_its_script_tag(): void
    {
        $name = 'A</script><meta http-equiv="refresh" content="0">&\'';
        $html = (string) $this->blade('<x-site.json-ld :data="$data" />', ['data' => [['name' => $name]]]);

        $this->assertStringNotContainsString('</script><meta', $html);
        // Tags and quotes as JSON_HEX_TAG and JSON_HEX_QUOT write them, escaped.
        $this->assertStringContainsString(substr((string) json_encode('</script><meta http-equiv="refresh"', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT), 1, -1), $html);
        $this->assertSame([['name' => $name]], $this->jsonLd($html));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(static fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }
}
