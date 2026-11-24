<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\LandingStage;
use App\Support\Site\AppWords;
use App\Support\Site\GamePreload;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The landing's stage (the home page at / and /es; Hillel, 2026-11-16: "make next-final the main home page"): it shows
 * the landing's song on its stage from the song's own data, in English and Spanish, with a dock that plays from any
 * scene; it's its own document with its own sheet and the site's footer; it's the site's home for search (canonical, in
 * the sitemap, counted as the home section); and the candidates' old addresses lead to it. What the landing carries for
 * growth (the visitor's source, mudeo arrivals, the funnel) is LandingPageTest's.
 */
final class LandingStagePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_song_plays_twice_and_the_voices_join_one_by_one_the_first_time(): void
    {
        $this->amazingGrace();

        $page = $this->get('/')->assertOk();
        $stage = $page->viewData('stage');

        // The player is asked for two passes, and told the beat each voice joins on: the sung lines' first beats.
        $this->assertSame(2, $stage['song']['passes']);
        $starts = array_column($stage['phrases'], 'from');
        $this->assertSame(['alto', 'tenor', 'bass'], array_keys($stage['joins']));
        $this->assertSame([$starts[1], $starts[2], $starts[3]], array_values($stage['joins']));
        $this->assertSame($stage['joins'], $stage['song']['joins']);
        foreach ($stage['joins'] as $beat) {
            $this->assertGreaterThan(0.0, $beat);
            $this->assertLessThan($stage['beats'], $beat);
        }
        // The page says the same beats on its lanes, the length of both passes, and has the line that says who sings.
        $html = (string) $page->getContent();
        $this->assertStringContainsString('data-passes="2"', $html);
        $this->assertStringContainsString('data-voice="soprano" data-name="soprano" data-title="Soprano" data-join="0"', $html);
        $this->assertStringContainsString('data-title="Alto" data-join="'.$stage['joins']['alto'].'"', $html);
        $seconds = (int) round($stage['beats'] * 2 * 60 / $stage['song']['tempo']);
        $this->assertSame(sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60), $stage['seconds']);
        $page->assertSee('0:00 / '.$stage['seconds'])->assertSee('data-lnf-now', false)
            ->assertSee('data-first=":part starts"', false)->assertSee('data-all="All four together. Tap a voice to hear it alone."', false);
        $this->get('/es')->assertOk()->assertSee('data-join="Se suma: :part"', false)->assertSee('Las cuatro juntas.', false);
        // Only the recorded voices sing here, and the page has the words for their download and for a failure.
        $this->assertTrue($stage['song']['realOnly']);
        // Their breaths are softened by the showreel's own code, shared as it is (every recorded voice's, on every
        // page and in the app: js/soften-breaths.js): the two files never differ.
        $this->assertFileEquals(resource_path('promo/showreel/js/breaths.js'), config('app.web_path').'/js/breaths.js', 'js/breaths.js is the showreel\'s breaths.js: copy it again');
        $page->assertSee('data-loading="Loading the voices…"', false)->assertSee('Couldn&#039;t load the voices. Try again.', false);
        $this->get('/es')->assertSee('Cargando las voces…', false)->assertSee('No se pudieron cargar las voces.', false);
    }

    public function test_joins_follow_the_sung_lines_and_share_the_song_when_there_are_too_few(): void
    {
        $this->assertSame(['alto' => 12.0, 'tenor' => 24.0, 'bass' => 36.0], LandingStage::joins(['soprano', 'alto', 'tenor', 'bass'], [0.0, 12.0, 24.0, 36.0], 48.0));
        $this->assertSame(['alto' => 12.0, 'tenor' => 24.0, 'bass' => 36.0], LandingStage::joins(['soprano', 'alto', 'tenor', 'bass'], [0.0, 12.0], 48.0));
        // A line that starts where the song ends, or at its start, joins nothing.
        $this->assertSame([], LandingStage::joins(['soprano', 'alto'], [0.0, 48.0], 48.0));
        $this->assertSame([], LandingStage::joins(['soprano'], [], 48.0));
    }

    public function test_the_top_line_has_the_site_headers_game_mode_switch(): void
    {
        $this->amazingGrace();

        $html = (string) $this->get('/')->assertOk()->getContent();
        // The same control as the site header's (its component), before Language and appearance, and again in that
        // menu for a phone (Hillel, 2026-11-16: "combine the two buttons"); light and dark only there. A third, the same
        // switch, is the optional Game mode card under "New to harmony?" (the approved blueprint, 2026-11-23).
        $this->assertSame(3, substr_count($html, 'data-game-toggle'));
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertLessThan(strpos($html, 'data-look-menu'), strpos($html, 'data-game-toggle'));
        $this->assertSame(1, substr_count($html, 'data-look-menu'));
        $this->assertSame(3, substr_count($html, 'data-look-mode="'));
        $this->assertStringNotContainsString('data-lnf-theme', $html);
        $this->assertStringContainsString('/css/game-toggle.css', $html);
        // Its rules are the site sheet's own block, copied as it is: the copy never drifts.
        $web = (string) config('app.web_path');
        $input = (string) file_get_contents($web.'/css/input.css');
        $from = strpos($input, '/* The Game mode toggle (the design agent');
        $to = strpos($input, '/* game-elements:start');
        $this->assertNotFalse($from);
        $this->assertNotFalse($to);
        $block = rtrim(substr($input, (int) $from, (int) $to - (int) $from))."\n";
        $this->assertStringEndsWith($block, (string) file_get_contents($web.'/css/game-toggle.css'), 'css/game-toggle.css is input.css\'s Game mode toggle block: copy it again');
    }

    public function test_the_stage_is_the_songs_own_notes_words_and_player(): void
    {
        $this->amazingGrace();

        $page = $this->get('/')->assertOk();
        $stage = $page->viewData('stage');

        $this->assertSame('amazing-grace', $stage['song']['song']);
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column($stage['lanes'], 'key'));
        // Every note of every part is a gem in the page, each with where it sits and (when it has one) its syllable.
        $notes = array_sum(array_map(static fn (array $lane): int => count($lane['notes']), $stage['lanes']));
        $this->assertGreaterThan(0, $notes);
        $this->assertSame($notes, substr_count((string) $page->getContent(), 'class="lnf-g gem-note"'));
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

        // The song page's player and its hooks, this page's own script, and the landing's funnel.
        $page->assertSee('data-player', false)->assertSee('data-song', false)->assertSee('data-play', false)
            ->assertSee('data-voice="alto"', false)->assertSee('data-hear-alone', false)
            ->assertSee('/js/public/mixer.js', false)->assertSee('/js/public/landing.js', false)
            ->assertSee('/js/public/funnel.js', false)->assertSee('<script type="application/json" data-funnel>', false);
        $this->assertSame(4, substr_count((string) $page->getContent(), 'data-funnel-start'));
        $html = (string) $page->getContent();
        // One play button for the player (the dock's disc); the first scene's big button presses it.
        $this->assertSame(1, substr_count($html, ' data-play '));
        $this->assertSame(1, substr_count($html, 'data-lnf-play'));
        // The dock is Play, the time and Start singing: a voice is heard alone by its lane (Hillel, 2026-11-16: "remove
        // the S A T B buttons from the footer"). The two Learn links start on the melody.
        $this->assertStringNotContainsString('data-lnf-voice', $html);
        $this->assertStringNotContainsString('lnf-vo', $html);
        // A voice is heard alone by its lane's Solo; the Hear scene has no button of its own (the review, 2026-11-23).
        $this->assertSame(0, substr_count($html, 'data-lnf-hear'));
        $this->assertSame(2, substr_count($html, 'data-lnf-learn'));
        $page->assertSee('Learn the soprano part')->assertSee('4 voices · '.$stage['seconds'])
            ->assertSee('Start lesson one')->assertSee('class="lnf-start"', false);
        // Stop is a stop: the word and the square, never a pause.
        $this->assertStringNotContainsStringIgnoringCase('pause', strip_tags((string) strstr((string) strstr($html, '<header class="lnf-top"'), '<div class="lnf-foot-wrap">', true)));
        // All eight scenes are in the page, in order, for a reader with no script: five on the stage, three after it
        // (the choir scene folded into the Leaders' way in and /for/choir-directors, 2026-11-23).
        $at = 0;
        foreach ([0, 1, 2, 3, 4, 5, 7, 8] as $scene) {
            $next = strpos($html, 'id="lnf-s'.$scene.'"');
            $this->assertNotFalse($next);
            $this->assertGreaterThan($at, $next);
            $at = $next;
        }
        $this->assertGreaterThan(strpos($html, 'id="lnf-s4"'), strpos($html, 'data-lnf-tail'));
        $this->assertLessThan(strpos($html, 'id="lnf-s5"'), strpos($html, 'data-lnf-tail'));
        // The home page is on the arcade screen in game mode, as the public layout marks it (data-gs).
        $this->assertMatchesRegularExpression('/<html[^>]*\bdata-gs\b/', (string) $page->getContent());
    }

    public function test_lesson_one_is_previewed_in_the_apps_own_words_without_the_mic(): void
    {
        $this->amazingGrace();

        $html = (string) $this->get('/')->assertOk()->getContent();
        $at = strpos($html, 'class="lnf-glass lnf-lesson"');
        $this->assertNotFalse($at, 'the preview is in the free section');
        $this->assertGreaterThan(strpos($html, 'id="lnf-s5"'), $at);
        $card = substr($html, $at, (int) strpos($html, '</figure>', $at) - $at);
        foreach (['capture.listening', 'results.note.yours', 'listening.suits.highMiddle'] as $key) {
            $this->assertStringContainsString(e(AppWords::get($key)), $card, $key);
        }
        $this->assertStringContainsString('>A3<', $card);
        // Still: no control in it and nothing that asks for the mic; Start lesson one beside it is the way in.
        $this->assertStringNotContainsString('<button', $card);
        $this->assertStringNotContainsString('<a ', $card);
        $this->assertStringNotContainsString('getUserMedia', $html);
        $this->assertSame(4, substr_count($html, 'data-funnel-start'));

        $this->assertStringContainsString('>La3<', (string) $this->get('/es')->assertOk()->getContent());
    }

    public function test_without_the_song_the_page_still_reads(): void
    {
        $page = $this->get('/')->assertOk();

        $this->assertNull($page->viewData('stage'));
        $page->assertDontSee('data-song', false)->assertDontSee('lnf-dock', false)->assertSee('id="lnf-s8"', false)->assertSee('/learn/1-comfortable', false);
    }

    public function test_it_is_the_sites_home_for_search_in_both_languages(): void
    {
        $this->amazingGrace();
        config(['ops.indexing' => true]);

        foreach (['/' => 'en', '/es' => 'es'] as $path => $language) {
            $page = $this->get($path)->assertOk();
            $html = (string) $page->getContent();

            // Indexable once the site is: no noindex in the page or its header, and its own canonical address.
            // (Spanish is indexed once that language is open: Languages' own test.)
            if ($language === 'en') {
                $this->assertFalse($page->headers->has('X-Robots-Tag'), $path);
                $this->assertStringNotContainsString('noindex', $html, $path);
            }
            $this->assertStringContainsString('<link rel="canonical" href="'.rtrim((string) config('app.url'), '/').($path === '/' ? '/' : $path).'">', $html, $path);
            $this->assertStringContainsString('<html lang="'.$language.'"', $html, $path);
            // Its link card, and its structured data with the four questions it shows.
            $this->assertStringContainsString('<meta property="og:image" content="', $html, $path);
            $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html, $path);
            $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'), $path);
            $this->assertSame(count(LandingStage::QUESTIONS), substr_count($html, '"@type":"Question"'), $path);
        }
        // The title and description search already knows.
        $this->get('/')->assertSee('<title>'.e(__('pages.landing.title')).'</title>', false)->assertSee('<meta name="description" content="'.e(__('pages.landing.description')).'">', false);
        // In the sitemap, both of them.
        $paths = app(SitemapPaths::class)->all();
        $this->assertContains('/', $paths);
    }

    public function test_the_candidates_old_addresses_lead_to_the_home_page_for_good(): void
    {
        foreach (LandingStage::GONE as $gone) {
            $this->get('/'.$gone)->assertStatus(301)->assertRedirect('/');
            $this->get('/es/'.$gone)->assertStatus(301)->assertRedirect('/es');
            $this->get('/'.$gone.'?ref=x')->assertStatus(301);
        }
        $this->assertSame(['next-grok', 'next-chatgpt', 'next-gemini', 'next-claude', 'next-final'], LandingStage::GONE);
        // No candidate is counted any more, and none is a page of the site.
        $this->assertSame([], array_values(array_filter((array) config('counts.kinds'), static fn (string $kind): bool => str_starts_with($kind, 'sv_next'))));
        $this->assertStringNotContainsString('next-', (string) $this->get('/sitemap.xml')->assertOk()->getContent());
    }

    public function test_the_spanish_page_is_in_spanish_and_stays_in_it(): void
    {
        $this->amazingGrace();

        $page = $this->get('/es')->assertOk();

        $page->assertSee('Canta armonías')->assertSee('Reproducir Amazing Grace')->assertSee('Canta todas las voces')->assertSee('Aprendices');
        // Its links to pages that have a Spanish stay in Spanish; its own two languages name themselves.
        $page->assertSee('href="/es/songs"', false)->assertSee('hreflang="en"', false);
        // The locale it set for itself is put back.
        $this->assertSame('en', app()->getLocale());
    }

    public function test_it_counts_as_the_home_section_by_the_sites_own_script(): void
    {
        $this->amazingGrace();
        config(['counts.enabled' => true]);

        foreach (['/', '/es'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            // The footer's count (js/public/site.js sends it, with Admin > Live's heartbeat): the home section, once.
            $this->assertMatchesRegularExpression('#<script type="application/json" data-site-visit>\{"kind":"sv_(home|other)"\}</script>#', $html, $path);
            $this->assertSame(1, substr_count($html, 'data-site-visit'), $path);
            $this->assertStringContainsString('/js/public/site.js', $html, $path);
            $this->assertStringNotContainsString('sv_next', $html, $path);
        }
        $this->assertSame('home', SiteVisit::section('/'));
        $this->postJson('/api/counts', ['events' => [['kind' => 'sv_next_final']]]);
        $this->assertSame([], DB::table('usage_counts')->pluck('kind')->all());
    }

    public function test_it_is_its_own_document_with_its_own_sheet(): void
    {
        $this->amazingGrace();

        foreach (['/', '/es'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            // Its own sheet and the Game mode switch's; none of the site's sheets. Game mode's dress (css/landing-game.css)
            // is never a link in the page: its address is a meta, the site's preloader asks for it and the fonts (Figtree
            // outside game mode), and js/game-css.js links it right after the page's own sheet, in game mode only.
            $this->assertSame(2, preg_match_all('/<link rel="stylesheet"/', $html), $path);
            $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/game-toggle\.css(\?v=[0-9a-f]+)?">#', $html, $path);
            $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/landing\.css(\?v=[0-9a-f]+)?">#', $html, $path);
            $this->assertMatchesRegularExpression('#<meta name="game-css" content="/css/landing-game\.css(\?v=[0-9a-f]+)?">#', $html, $path);
            $this->assertStringContainsString((string) GamePreload::tag(), $html, $path);
            $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/landing\.css(\?v=[0-9a-f]+)?">\s*<script src="/js/game-css\.js(\?v=[0-9a-f]+)?"></script>#', $html, $path);
            $this->assertStringNotContainsString('stackapella-pixel', $html, $path);
            foreach (['/site-css/', 'css-family', 'cabinet.js'] as $piece) {
                $this->assertStringNotContainsString($piece, $html, $path);
            }
        }
        // Every class the page uses is in its sheets, game mode's included (or is one the player's script adds).
        $html = (string) $this->get('/')->getContent();
        $sheet = file_get_contents(config('app.web_path').'/css/landing.css').file_get_contents(config('app.web_path').'/css/game-toggle.css').file_get_contents(config('app.web_path').'/css/landing-game.css');
        preg_match_all('/class="([^"]*)"/', $html, $found);
        $classes = array_unique(array_filter(explode(' ', implode(' ', $found[1]))));
        $missing = array_values(array_filter($classes, static fn (string $class): bool => ! str_contains($sheet, '.'.$class)));
        $this->assertSame([], $missing);
    }

    private function amazingGrace(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }
}
