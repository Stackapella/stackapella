<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Flags;
use App\Support\Pages\NextFinal;
use App\Support\Site\SiteCss;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /next-final, the candidate for the final next landing page: it shows the landing's song on its stage from the song's
 * own data, in English and Spanish, with a dock that plays from any scene; it's its own document with its own sheet;
 * and it's private: never indexed, in no sitemap, with no alternates, counted only as its own kind (in no growth
 * section), and the landing at / is as it was.
 */
final class NextFinalPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_stage_is_the_songs_own_notes_words_and_player(): void
    {
        $this->amazingGrace();

        $page = $this->get('/next-final')->assertOk();
        $stage = $page->viewData('stage');

        $this->assertSame('amazing-grace', $stage['song']['song']);
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column($stage['lanes'], 'key'));
        // Every note of every part is a gem in the page, each with where it sits and (when it has one) its syllable.
        $notes = array_sum(array_map(static fn (array $lane): int => count($lane['notes']), $stage['lanes']));
        $this->assertGreaterThan(0, $notes);
        $this->assertSame($notes, substr_count((string) $page->getContent(), 'class="lnf-g"'));
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
            ->assertSee('/js/public/mixer.js', false)->assertSee('/js/public/landing-next-final.js', false)
            ->assertDontSee('/js/public/funnel.js', false)->assertDontSee('data-funnel', false);
        $html = (string) $page->getContent();
        // One play button for the player (the dock's disc); the first scene's big button presses it.
        $this->assertSame(1, substr_count($html, ' data-play '));
        $this->assertSame(1, substr_count($html, 'data-lnf-play'));
        // The dock: a button for each voice, and Start singing; the Hear scene's button and the two Learn links start
        // on the melody.
        foreach ($stage['lanes'] as $lane) {
            $this->assertStringContainsString('data-lnf-voice="'.$lane['key'].'"', $html);
        }
        $this->assertSame(1, substr_count($html, 'data-lnf-hear'));
        $this->assertSame(2, substr_count($html, 'data-lnf-learn'));
        $page->assertSee('Hear the soprano alone')->assertSee('Learn the soprano part')->assertSee('4 voices · '.$stage['seconds'])
            ->assertSee('Start lesson one')->assertSee('class="lnf-start"', false);
        // Stop is a stop: the word and the square, never a pause.
        $this->assertStringNotContainsStringIgnoringCase('pause', strip_tags($html));
        // All nine scenes are in the page, in order, for a reader with no script: five on the stage, four after it.
        $at = 0;
        foreach (range(0, 8) as $scene) {
            $next = strpos($html, 'id="lnf-s'.$scene.'"');
            $this->assertNotFalse($next);
            $this->assertGreaterThan($at, $next);
            $at = $next;
        }
        $this->assertGreaterThan(strpos($html, 'id="lnf-s4"'), strpos($html, 'data-lnf-tail'));
        $this->assertLessThan(strpos($html, 'id="lnf-s5"'), strpos($html, 'data-lnf-tail'));
        // The choir card shows each sample singer's own initials.
        foreach (['PS', 'SL', 'KO', 'TO'] as $initials) {
            $this->assertStringContainsString('<span class="lnf-orb">'.$initials.'</span>', $html);
        }
        // Calm only: it asks for no arcade screen.
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\bdata-gs\b/', (string) $page->getContent());
    }

    public function test_without_the_song_the_page_still_reads(): void
    {
        $page = $this->get('/next-final')->assertOk();

        $this->assertNull($page->viewData('stage'));
        $page->assertDontSee('data-song', false)->assertDontSee('lnf-dock', false)->assertSee('id="lnf-s8"', false)->assertSee('/learn/1-comfortable', false);
    }

    public function test_it_is_private_in_both_languages(): void
    {
        $this->amazingGrace();

        foreach (['/next-final' => 'en', '/es/next-final' => 'es'] as $path => $language) {
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
        $this->assertNotContains('/next-final', $paths);
        $this->assertNotContains('/es/next-final', $paths);
        $this->assertStringNotContainsString('next-final', (string) $this->get('/sitemap.xml')->assertOk()->getContent());
        // Linked from nowhere: not from the landing, in either language.
        $this->assertStringNotContainsString('next-final', (string) $this->get('/')->assertOk()->getContent());
        $this->assertStringNotContainsString('next-final', (string) $this->get('/es')->getContent());
    }

    public function test_the_spanish_page_is_in_spanish_and_stays_in_it(): void
    {
        $this->amazingGrace();

        $page = $this->get('/es/next-final')->assertOk();

        $page->assertSee('Armonía,')->assertSee('Reproducir Amazing Grace')->assertSee('Escucha cuatro voces y quédate con una.');
        // Its links to pages that have a Spanish stay in Spanish; its own two languages name themselves.
        $page->assertSee('href="/es/songs"', false)->assertSee('hreflang="en"', false)->assertSee('href="/next-final"', false);
        // The locale it set for itself is put back.
        $this->assertSame('en', app()->getLocale());
    }

    public function test_it_counts_only_as_its_own_kind_and_in_no_growth_section(): void
    {
        $this->amazingGrace();
        config(['counts.enabled' => true]);

        foreach (['/next-final', '/es/next-final'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('<script type="application/json" data-lnf-visit>{"kind":"sv_next_final"}</script>', $html, $path);
            $this->assertSame(1, substr_count($html, 'data-lnf-visit'), $path);
            $this->assertStringNotContainsString('data-site-visit', $html, $path);
        }

        $this->assertSame('next_final', SiteVisit::section('next-final'));
        $this->assertSame('next_final', SiteVisit::section('es/next-final'));
        // Its own kind is kept; it is none of the sections any growth read adds up.
        $this->assertContains('sv_next_final', (array) config('counts.kinds'));
        $this->assertNotContains('next_final', SiteVisit::SECTIONS);
        $this->postJson('/api/counts', ['events' => [['kind' => 'sv_next_final']]])->assertNoContent();
        $this->assertSame(['sv_next_final'], DB::table('usage_counts')->pluck('kind')->all());
    }

    public function test_it_is_its_own_document_with_its_own_sheet(): void
    {
        $this->amazingGrace();

        foreach (['/next-final', '/es/next-final'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            // One sheet, its own, and the font asked for at once; none of the site's sheets or its game scripts.
            $this->assertSame(1, preg_match_all('/<link rel="stylesheet"/', $html), $path);
            $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/landing-next-final\.css(\?v=[0-9a-f]+)?">#', $html, $path);
            $this->assertStringContainsString('<link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>', $html, $path);
            foreach (['/site-css/', 'game-css.js', '/js/public/site.js', 'css-family'] as $piece) {
                $this->assertStringNotContainsString($piece, $html, $path);
            }
        }
        // /next-claude keeps its own family; this page is in none.
        $this->assertSame('lncl', SiteCss::family('/next-claude'));
        $this->assertNotSame('lncl', SiteCss::family('/next-final'));
        // Every class the page uses is in its sheet (or is one the player's script adds).
        $html = (string) $this->get('/next-final')->getContent();
        $sheet = (string) file_get_contents(config('app.web_path').'/css/landing-next-final.css');
        preg_match_all('/class="([^"]*)"/', $html, $found);
        $classes = array_unique(array_filter(explode(' ', implode(' ', $found[1]))));
        $missing = array_values(array_filter($classes, static fn (string $class): bool => ! str_contains($sheet, '.'.$class)));
        $this->assertSame([], $missing);
    }

    public function test_the_choir_scene_says_a_group_takes_plus_while_it_does(): void
    {
        $this->amazingGrace();
        $line = 'Starting a choir group is part of Plus. Everyone you invite joins free.';

        $this->get('/next-final')->assertOk()->assertDontSee($line);
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        $this->get('/next-final')->assertOk()->assertSee($line);
        $this->get('/es/next-final')->assertOk()->assertSee('Crear un grupo de coro es parte de Plus.');
    }

    public function test_a_singers_initials_are_their_names_first_letters(): void
    {
        $this->assertSame('PS', NextFinal::initials('Priya Shah'));
        $this->assertSame('TO', NextFinal::initials('  tom   okafor '));
        $this->assertSame('ÁM', NextFinal::initials('Ángel María de la Cruz'));
        $this->assertSame('K', NextFinal::initials('Kim'));
        $this->assertSame('', NextFinal::initials(''));
    }

    private function amazingGrace(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }
}
