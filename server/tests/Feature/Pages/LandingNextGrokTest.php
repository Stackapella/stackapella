<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Flags;
use App\Support\Pages\LandingNextGrok;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The combined landing, candidate "grok" (/next-grok): kept out of search, the sitemap, the languages' links and the
 * growth totals, with its stage drawn from the song's own notes, in English and Spanish. The landing at / stays as it
 * is (LandingPageTest).
 */
final class LandingNextGrokTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_never_in_search_in_either_language(): void
    {
        $this->amazingGrace();
        // Even once the site is indexed.
        config(['ops.indexing' => true]);
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        foreach (['/next-grok', '/es/next-grok'] as $path) {
            $page = $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
            $html = (string) $page->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex">', $html, $path);
            $this->assertStringNotContainsString('rel="canonical"', $html, $path);
            $this->assertStringNotContainsString('hreflang="x-default"', $html, $path);
            $this->assertStringNotContainsString('rel="alternate" hreflang', $html, $path);
        }
    }

    public function test_it_is_in_no_sitemap_and_no_page_links_to_it(): void
    {
        $this->amazingGrace();
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        $this->assertStringNotContainsString('next-grok', (string) $this->get('/sitemap.xml')->getContent());
        $this->assertNotContains('/next-grok', app(SitemapPaths::class)->all());
        $this->assertStringNotContainsString('next-grok', (string) $this->get('/')->getContent());
    }

    public function test_its_visits_are_their_own_kind_and_no_growth_section(): void
    {
        $this->amazingGrace();
        config(['counts.enabled' => true]);
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        foreach (['/next-grok', '/es/next-grok'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<script type="application/json" data-site-visit>{"kind":"sv_next_grok"}</script>', false)
                // The landing's own counts (site_visit, the start funnel) never ride on it.
                ->assertDontSee('data-funnel', false);
        }
        // The growth totals and comparisons read SiteVisit::SECTIONS: a trial is none of them.
        $this->assertNotContains('next_grok', SiteVisit::SECTIONS);
        $this->assertSame('other', SiteVisit::section('next-grok'));
        $this->assertContains('sv_next_grok', (array) config('counts.kinds'));

        $this->postJson('/api/counts', ['events' => [['kind' => 'sv_next_grok']]])->assertNoContent();
        $this->assertSame(['sv_next_grok'], DB::table('usage_counts')->pluck('kind')->all());

        // The landing still counts as home.
        $this->get('/')->assertSee('{"kind":"sv_home"}', false);
    }

    public function test_the_stage_is_drawn_from_the_songs_own_notes(): void
    {
        $this->amazingGrace();

        $page = $this->get('/next-grok')->assertOk();
        $song = $page->viewData('song');
        $html = (string) $page->getContent();

        $this->assertSame('amazing-grace', $song['song']);
        // The whole song is in the page, so nothing is fetched to light it.
        $this->assertFalse($song['whole']);
        $this->assertSame($song['songBeats'], $song['beats']);
        $notes = array_sum(array_map(static fn (array $part): int => count($part['notes']), $song['parts']));
        $this->assertGreaterThan(0, $notes);
        $this->assertSame($notes, substr_count($html, 'class="s-lng-gem gem"'));
        // A gem sits where its note is: its start and length as shares of the song, its height from the part's range.
        foreach ($song['parts'] as $part) {
            foreach (LandingNextGrok::gems($part, $song) as $i => $gem) {
                $note = $part['notes'][$i];
                $this->assertEqualsWithDelta($note['beat'] / $song['beats'] * 100, $gem['left'], 0.001);
                $this->assertEqualsWithDelta($note['length'] / $song['beats'] * 100, $gem['width'], 0.001);
                $this->assertGreaterThanOrEqual(0.0, $gem['top']);
                $this->assertLessThanOrEqual(1.0, $gem['top']);
            }
        }
        // The player's hooks (js/public/mixer.js), one lane a part, each to hear alone and to pick.
        $this->assertSame(1, substr_count($html, ' data-play '));
        $this->assertSame(count($song['parts']), substr_count($html, 'data-hear-alone'));
        $this->assertSame(count($song['parts']), substr_count($html, 'data-pick'));
        // Nine scenes, each a real address the rail links to.
        $this->assertSame(9, substr_count($html, 'data-lng-scene="'));
        $this->assertSame(9, substr_count($html, 'data-lng-rail="'));
        // Links into the app carry the page's own ref, never the landing's.
        $this->assertStringContainsString('ref=next-grok', $html);
        // No em or en dashes in what it says.
        $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', strip_tags((string) preg_replace('#<script.*?</script>#s', '', $html)));
    }

    public function test_the_words_are_cut_into_short_phrases_without_splitting_a_word(): void
    {
        $words = [
            ['beat' => 0.0, 'text' => 'A', 'joins' => true, 'br' => true],
            ['beat' => 1.0, 'text' => 'maz', 'joins' => true, 'br' => false],
            ['beat' => 3.0, 'text' => 'ing', 'joins' => false, 'br' => false],
            ['beat' => 11.0, 'text' => 'sound', 'joins' => true, 'br' => false],
            // Twelve beats in, but still inside a word: it stays with its phrase.
            ['beat' => 12.0, 'text' => 'ing', 'joins' => false, 'br' => false],
            ['beat' => 13.0, 'text' => 'that', 'joins' => false, 'br' => false],
            ['beat' => 24.0, 'text' => 'I', 'joins' => false, 'br' => true],
        ];

        $phrases = array_map(static fn (array $phrase): string => implode(' ', array_column($phrase, 'text')), LandingNextGrok::phrases($words));

        $this->assertSame(['A maz ing sound ing', 'that', 'I'], $phrases);
    }

    private function amazingGrace(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }
}
