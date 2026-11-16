<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\LandingNextGemini;
use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing's next version, the gemini variant (/next-gemini; docs/landing-next-gemini-plan.md): a candidate kept
 * apart from the landing. Never indexed, no canonical and no alternates, in no sitemap, linked from nowhere, counted
 * only under its own kind; its stage drawn by the server from the song's own notes; in English and Spanish.
 */
final class LandingNextGeminiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_never_indexed_has_no_canonical_or_alternates_and_is_in_no_sitemap(): void
    {
        config(['ops.indexing' => true]); // even once the site is indexed
        $this->amazingGrace();

        foreach ([LandingNextGemini::PATH, '/es'.LandingNextGemini::PATH] as $path) {
            $page = $this->get($path)->assertOk();
            $this->assertStringContainsString('noindex', (string) $page->headers->get('X-Robots-Tag'), $path);
            $page->assertSee('<meta name="robots" content="noindex, nofollow">', false);
            $html = (string) $page->getContent();
            $this->assertStringNotContainsString('rel="canonical"', $html, $path);
            $this->assertStringNotContainsString('rel="alternate"', $html, $path);
        }

        $this->assertStringNotContainsString('next-gemini', (string) $this->get('/sitemap.xml')->assertOk()->getContent());
        // Linked from nowhere: not the landing, the songs, the courses or pricing, in either language.
        foreach (['/', '/es', '/songs', '/courses', '/pricing'] as $path) {
            $this->assertStringNotContainsString('next-gemini', (string) $this->get($path)->assertOk()->getContent(), $path);
        }
    }

    public function test_its_visits_are_its_own_kind_and_no_site_sections(): void
    {
        config(['counts.enabled' => true]);
        $this->amazingGrace();

        $this->assertContains(LandingNextGemini::VISIT, (array) config('counts.kinds'));
        // Never a section: the growth totals sum SiteVisit's sections, so the page stays out of them.
        $this->assertNotContains(substr(LandingNextGemini::VISIT, 3), SiteVisit::SECTIONS);

        $html = (string) $this->get(LandingNextGemini::PATH)->assertOk()->getContent();
        $this->assertStringContainsString('data-lngm-visit>{"kind":"sv_next_gemini"}', $html);
        $this->assertStringNotContainsString('data-site-visit', $html);
        $this->assertStringNotContainsString('data-funnel', $html);

        config(['counts.enabled' => false]);
        $this->assertStringNotContainsString('data-lngm-visit', (string) $this->get(LandingNextGemini::PATH)->assertOk()->getContent());
    }

    public function test_the_stage_is_drawn_by_the_server_from_the_songs_own_notes(): void
    {
        $template = $this->amazingGrace();
        $data = (array) $template->currentVersion->data;
        $notes = array_sum(array_map(static fn (array $part): int => count((array) ($part['guide']['notes'] ?? [])), (array) $data['parts']));

        $page = $this->get(LandingNextGemini::PATH)->assertOk();
        $stage = $page->viewData('stage');
        $html = (string) $page->getContent();

        // The whole verse is in the page: a gem for every note of every part, each placed by its beat and pitch.
        $this->assertFalse($stage['song']['whole']);
        $this->assertSame($notes, array_sum(array_map(static fn (array $lane): int => count($lane['gems']), $stage['lanes'])));
        $this->assertSame($notes, substr_count($html, 'class="lngm-gem'));
        foreach ($stage['lanes'] as $lane) {
            foreach ($lane['gems'] as $gem) {
                $this->assertGreaterThanOrEqual(0.0, $gem['y']);
                $this->assertLessThanOrEqual(1.0, $gem['y']);
            }
            // Each lane is its voice for the player, a button that plays it alone, and leads to learning that part.
            $this->assertStringContainsString('data-voice="'.$lane['key'].'"', $html);
            $this->assertStringContainsString('song='.$template->slug.'&amp;part='.$lane['key'].'&amp;ref='.LandingNextGemini::REF, $html);
        }
        $this->assertSame(count($stage['lanes']), substr_count($html, 'data-hear-alone'));
        // One Play, the headline as words, and nothing that plays by itself.
        $this->assertSame(1, substr_count($html, 'data-play '));
        $page->assertSee('<h1 id="lngm-h">', false);
        $this->assertStringNotContainsString('autoplay', $html);
        // Its own sheet and script, and none of the site's or the game skin's.
        $this->assertStringContainsString('/css/landing-next-gemini.css', $html);
        $this->assertStringContainsString('/js/public/landing-next-gemini.js', $html);
        $this->assertStringNotContainsString('site.css', $html);
        $this->assertStringNotContainsString('game', $html);
    }

    public function test_the_last_scenes_counts_are_read_live(): void
    {
        $this->amazingGrace();

        $page = $this->get(LandingNextGemini::PATH)->assertOk();
        $counts = $page->viewData('counts');

        $this->assertSame(LandingNextGemini::counts(), $counts);
        foreach (['courses', 'songs', 'tools'] as $key) {
            $page->assertSee('<b>'.$counts[$key].'</b>', false);
        }
    }

    public function test_the_spanish_page_is_in_spanish_and_its_links_stay_in_spanish(): void
    {
        $this->amazingGrace();

        $page = $this->get('/es'.LandingNextGemini::PATH)->assertOk();
        $page->assertSee('<html lang="es"', false);
        $page->assertSee('Armonía,');
        $page->assertSee('href="/es/songs"', false);
        $page->assertSee('href="/es/pricing"', false);
        // The request's language never leaks into the next one.
        $this->assertSame('en', app()->getLocale());
        $this->get(LandingNextGemini::PATH)->assertOk()->assertSee('<html lang="en"', false)->assertSee('Harmony,');
    }

    public function test_the_page_opens_without_the_song(): void
    {
        $page = $this->get(LandingNextGemini::PATH)->assertOk();

        $this->assertNull($page->viewData('stage'));
        $page->assertSee('<h1 id="lngm-h">', false);
        $this->assertStringNotContainsString(' data-play ', (string) $page->getContent());
    }

    public function test_no_em_dashes_in_its_words(): void
    {
        foreach (['en', 'es'] as $language) {
            foreach ((array) trans('next_gemini', [], $language) as $key => $words) {
                $this->assertStringNotContainsString('—', (string) $words, $language.'.'.$key);
            }
        }
        // Every English sentence has its Spanish.
        $spanish = (array) json_decode((string) file_get_contents(lang_path('es/next_gemini.json')), true);
        foreach ((array) trans('next_gemini', [], 'en') as $key => $words) {
            $this->assertArrayHasKey((string) $words, $spanish, 'next_gemini.'.$key.' has no Spanish');
        }
    }

    private function amazingGrace(): Template
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        return $template->fresh(['currentVersion']);
    }
}
