<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\NextClaude;
use App\Support\Site\SiteCss;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /next-claude, a candidate for the next landing page: it shows the landing's song on its stage from the song's own
 * data, in English and Spanish, and it's private: never indexed, in no sitemap, with no alternates, counted only as
 * its own kind (in no growth section), and the landing at / is as it was.
 */
final class NextClaudePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_stage_is_the_songs_own_notes_words_and_player(): void
    {
        $this->amazingGrace();

        $page = $this->get('/next-claude')->assertOk();
        $stage = $page->viewData('stage');

        $this->assertSame('amazing-grace', $stage['song']['song']);
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column($stage['lanes'], 'key'));
        // Every note of every part is a gem in the page, each with where it sits and (when it has one) its syllable.
        $notes = array_sum(array_map(static fn (array $lane): int => count($lane['notes']), $stage['lanes']));
        $this->assertGreaterThan(0, $notes);
        $this->assertSame($notes, substr_count((string) $page->getContent(), 'class="s-lncl-g"'));
        foreach ($stage['lanes'] as $lane) {
            foreach ($lane['notes'] as $note) {
                $this->assertGreaterThanOrEqual(0.0, $note['y']);
                $this->assertLessThanOrEqual(1.0, $note['y']);
                $this->assertLessThan($stage['beats'], $note['beat']);
            }
            // "I sing this" is the song page's way into the app for the part.
            $this->assertStringContainsString('song=amazing-grace', $lane['learn']);
            $this->assertStringContainsString('part='.$lane['key'], $lane['learn']);
        }
        // The sung lines are the melody's words, whole.
        $this->assertNotSame([], $stage['phrases']);
        foreach ($stage['phrases'] as $phrase) {
            $this->assertLessThan($phrase['to'], $phrase['from']);
            $this->assertNotSame([], $phrase['words']);
        }

        // The song page's player and its hooks, and this page's own script; never the landing's own counts.
        $page->assertSee('data-player', false)->assertSee('data-song', false)->assertSee('data-play', false)
            ->assertSee('data-voice="alto"', false)->assertSee('data-hear-alone', false)
            ->assertSee('/js/public/mixer.js', false)->assertSee('/js/public/landing-next-claude.js', false)
            ->assertDontSee('/js/public/funnel.js', false)->assertDontSee('data-funnel', false);
        // All nine scenes are in the page, in order, for a reader with no script.
        foreach (range(0, 8) as $scene) {
            $page->assertSee('id="lncl-s'.$scene.'"', false);
        }
        // Calm only: it asks for no arcade screen.
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\bdata-gs\b/', (string) $page->getContent());
    }

    public function test_without_the_song_the_page_still_reads(): void
    {
        $page = $this->get('/next-claude')->assertOk();

        $this->assertNull($page->viewData('stage'));
        $page->assertDontSee('data-song', false)->assertSee('id="lncl-s8"', false)->assertSee('/learn/1-comfortable', false);
    }

    public function test_it_is_private_in_both_languages(): void
    {
        $this->amazingGrace();

        foreach (['/next-claude' => 'en', '/es/next-claude' => 'es'] as $path => $language) {
            $page = $this->get($path)->assertOk();
            $html = (string) $page->getContent();

            $page->assertHeader('X-Robots-Tag', 'noindex');
            $this->assertStringContainsString('<meta name="robots" content="noindex">', $html, $path);
            $this->assertStringNotContainsString('rel="canonical"', $html, $path);
            $this->assertStringNotContainsString('rel="alternate"', $html, $path);
            $this->assertStringNotContainsString('og:locale:alternate', $html, $path);
            $this->assertStringContainsString('<html lang="'.$language.'"', $html, $path);
        }

        // In no sitemap, and no page the sitemap lists is this one.
        $paths = app(SitemapPaths::class)->all();
        $this->assertNotContains('/next-claude', $paths);
        $this->assertNotContains('/es/next-claude', $paths);
        $this->assertStringNotContainsString('next-claude', (string) $this->get('/sitemap.xml')->assertOk()->getContent());
        // Linked from nowhere: not from the landing, in either language.
        $this->assertStringNotContainsString('next-claude', (string) $this->get('/')->assertOk()->getContent());
        $this->assertStringNotContainsString('next-claude', (string) $this->get('/es')->getContent());
    }

    public function test_the_spanish_page_is_in_spanish_and_stays_in_it(): void
    {
        $this->amazingGrace();

        $page = $this->get('/es/next-claude')->assertOk();

        $page->assertSee('Armonía,')->assertSee('Reproducir Amazing Grace')->assertSee('Escucha cuatro voces y quédate con una.');
        // Its links to pages that have a Spanish stay in Spanish; its own two languages name themselves.
        $page->assertSee('href="/es/songs"', false)->assertSee('hreflang="en"', false)->assertSee('href="/next-claude"', false);
        // The locale it set for itself is put back.
        $this->assertSame('en', app()->getLocale());
    }

    public function test_it_counts_only_as_its_own_kind_and_in_no_growth_section(): void
    {
        $this->amazingGrace();
        config(['counts.enabled' => true]);

        foreach (['/next-claude', '/es/next-claude'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('<script type="application/json" data-site-visit>{"kind":"sv_next_claude"}</script>', $html, $path);
            $this->assertSame(1, substr_count($html, 'data-site-visit'), $path);
        }

        $this->assertSame('next_claude', SiteVisit::section('next-claude'));
        $this->assertSame('next_claude', SiteVisit::section('es/next-claude'));
        // Its own kind is kept; it is none of the sections any growth read adds up.
        $this->assertContains('sv_next_claude', (array) config('counts.kinds'));
        $this->assertNotContains('next_claude', SiteVisit::SECTIONS);
        $this->postJson('/api/counts', ['events' => [['kind' => 'sv_next_claude']]])->assertNoContent();
        $this->assertSame(['sv_next_claude'], DB::table('usage_counts')->pluck('kind')->all());
    }

    public function test_it_has_its_own_small_stylesheet(): void
    {
        $this->amazingGrace();

        $this->assertSame('lncl', SiteCss::family('/next-claude'));
        $this->assertSame('lncl', SiteCss::family('/es/next-claude'));
        // Every class the page shows is one its family's sheet keeps, so it never falls back to the whole site's.
        foreach (['/next-claude', '/es/next-claude'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertSame([], SiteCss::missing((string) config('app.web_path'), 'lncl', $html), $path);
        }
    }

    public function test_the_singer_credit_names_the_parts_in_the_pages_language(): void
    {
        $sungBy = new \ReflectionMethod(NextClaude::class, 'sungBy');
        $names = ['soprano' => 'Soprano', 'alto' => 'Contralto', 'tenor' => 'Tenor', 'bass' => 'Bajo'];
        $credit = 'Amazing Grace. Soprano and alto sung by Ana Ruiz. Tenor and bass by the practice voice.';

        $this->assertSame('Soprano and alto sung by Ana Ruiz', $sungBy->invoke(null, $credit, $names));
        $this->assertNull($sungBy->invoke(null, 'Practice voices.', $names));
        app()->setLocale('es');
        $this->assertSame('Soprano y contralto, en la voz de Ana Ruiz', $sungBy->invoke(null, $credit, $names));
        // A credit that names anything but the song's parts is shown as written.
        $this->assertSame('The melody sung by Ana Ruiz', $sungBy->invoke(null, 'The melody sung by Ana Ruiz.', $names));
    }

    private function amazingGrace(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }
}
