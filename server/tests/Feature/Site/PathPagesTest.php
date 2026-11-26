<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Flags;
use App\Support\Legal\LegalText;
use App\Support\Site\PathPages;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home's paths with pages of their own (Hillel, 2026-11-23: "the home's Leaders path has its own page; do the same
 * for Singers and Learners"): /for/learners and /for/singers name their people, give one way in, then the site's own
 * songs, courses, tools, guides and questions for that way in, and never a word about AI (that's /ai-harmony-singing's).
 */
final class PathPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_learners_read_in_order_from_lesson_one_with_no_account(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $html = (string) $this->get('/for/learners')->assertOk()->getContent();

        $this->inOrder($html, [
            '<h1 id="page-h" class="s-aud-kick">'.e(__('site.for.pages.learners.h1')).'</h1>',
            '/learn/1-comfortable?ref=for-learners',
            e(PathPages::split((string) __('site.for.pages.learners.heads.hear'))[1]),
            'href="/songs/amazing-grace"',
            'href="/guides/what-is-harmony"',
            e(PathPages::split((string) __('site.for.pages.learners.heads.sing'))[1]),
            'href="/features/course"',
            'href="/courses/perfect-your-pitch"',
            'href="/tools/vocal-range-test"',
            'href="/guides/sing-harmony-with-yourself"',
            e(PathPages::split((string) __('site.for.pages.learners.heads.better'))[1]),
            'href="/for/beginners"',
            'id="faq-h"',
            'id="end-h"',
        ]);
        $this->assertStringContainsString(e(__('pages.landing.faq.read_music.0')), $html);
        $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'));
        $this->assertSame(1, substr_count($html, 'href="/for/beginners"'), 'the beginners page, once');
    }

    public function test_singers_read_in_order_from_recording_a_part_of_the_homes_song(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $html = (string) $this->get('/for/singers')->assertOk()->getContent();

        $this->inOrder($html, [
            '<h1 id="page-h" class="s-aud-kick">'.e(__('site.for.pages.singers.h1')).'</h1>',
            '/t/amazing-grace?ref=for-singers',
            e(PathPages::split((string) __('site.for.pages.singers.heads.pick'))[1]),
            'href="/songs"',
            e(PathPages::split((string) __('site.for.pages.singers.heads.record'))[1]),
            'href="/features/studio"',
            'href="/courses/sight-singing"',
            e(PathPages::split((string) __('site.for.pages.singers.heads.mix'))[1]),
            'href="/tools/audio-latency-test"',
            'href="/guides/record-vocal-harmonies-at-home"',
            e(PathPages::split((string) __('site.for.pages.singers.heads.share'))[1]),
            'href="/features/share"',
            'href="/for/friends-and-family"',
            'href="/pricing"',
            'href="/for/a-cappella"',
            'id="faq-h"',
            'id="end-h"',
        ]);
        $this->assertSame(1, substr_count($html, 'href="/for/a-cappella"'), 'the a cappella page, once');
        $this->assertStringContainsString(e(__('plus.items.storage.0')), $html);
    }

    public function test_in_spanish_the_guides_are_spanish_where_they_are_translated(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        // Each card reads the guide in the page's language, as the guide's own page does (marketing's Spanish check).
        $learners = (string) $this->get('/es/for/learners')->assertOk()->getContent();
        $this->assertStringContainsString(e(app(LegalText::class)->read('what-is-harmony', 'es', 'guides')['title']), $learners);
        $this->assertStringNotContainsString(e(app(LegalText::class)->read('what-is-harmony', 'en', 'guides')['title']), $learners);
        $singers = (string) $this->get('/es/for/singers')->assertOk()->getContent();
        $this->assertStringContainsString(e(app(LegalText::class)->read('record-vocal-harmonies-at-home', 'es', 'guides')['title']), $singers);
    }

    public function test_who_its_for_plays_real_songs_and_answers_its_questions(): void
    {
        app(SyncOfficialTemplates::class)->handle();

        $response = $this->get('/for')->assertOk();
        $html = (string) $response->getContent();
        // Its own questions, in marketing's order, as structured data too (the free answer's prices vary by country).
        $this->assertSame(5, substr_count($html, '<summary>'));
        $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'));
        $this->assertStringContainsString('CF-IPCountry', (string) $response->headers->get('Vary'));
        foreach (['account', 'whole_choir'] as $key) {
            $this->assertStringContainsString(e(__('site.for.index.faq.'.$key.'.0')), $html);
        }
        // Each way in's three steps.
        $this->assertSame(9, substr_count($html, '<li><b aria-hidden="true">'));
        // The songs: the site's own player, each to its page, never one withheld somewhere.
        if (substr_count($html, 'data-player') > 0) {
            $this->assertStringContainsString('/js/public/mixer.js', $html);
            $this->assertMatchesRegularExpression('#href="/songs/[a-z-]+" class="s-arrow"#', $html);
        }
        $this->assertStringNotContainsString('/songs/happy-birthday', $html);
    }

    public function test_who_its_for_says_what_a_group_takes_the_same_in_its_band_and_its_answer(): void
    {
        config(['flags.plus_teams' => ['kind' => 'feature', 'values' => ['off', 'on']], 'flags.choir_seats' => ['kind' => 'feature', 'values' => ['off', 'on']]]);

        // Teaming up off, then on, then choir seats too: the directors band's cost line and the whole-choir answer agree.
        foreach ([[[], 'Starting a group is free.', 'Start a group for free'], [['plus_teams'], 'Starting a group takes Plus. Singers join free', 'Start a group with Plus'], [['choir_seats'], 'Starting a group is free, and each singer needs a seat', 'Each singer needs a seat, which you buy']] as [$on, $band, $answer]) {
            foreach ($on as $flag) {
                app(Flags::class)->set($flag, 'on', null, 'test');
            }
            $html = (string) $this->get('/for')->assertOk()->getContent();
            $this->assertStringContainsString($band, $html, implode(',', $on) ?: 'off');
            $this->assertStringContainsString($answer, $html, implode(',', $on) ?: 'off');
        }
    }

    public function test_neither_says_a_word_about_ai_in_either_language(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        foreach (['/for/learners', '/for/singers', '/es/for/learners', '/es/for/singers'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $words = html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $html));
            $this->assertDoesNotMatchRegularExpression('/\b(AI|IA)\b/u', $words, $path);
            foreach (['/ai-harmony-singing', '/armonias-con-ia', '/ai-singing'] as $ai) {
                $this->assertStringNotContainsString($ai, $html, $path);
            }
            $this->assertStringNotContainsString('/your-choir', $html, $path);
            $this->assertStringNotContainsString(e(__('plus.items.choir.0')), $html, $path);
        }
        $this->get('/es/for/learners')->assertSee('<html lang="es"', false)->assertSee('<h1 id="page-h" class="s-aud-kick">Para aprendices</h1>', false);
    }

    public function test_a_visitors_own_ref_stays_theirs(): void
    {
        $html = (string) $this->get('/for/singers?ref=reddit-singing')->assertOk()->getContent();
        $this->assertStringContainsString('/t/amazing-grace?ref=reddit-singing', $html);
        $this->assertStringNotContainsString('ref=for-singers', $html);
    }

    public function test_the_homes_paths_lead_to_their_pages_which_are_in_the_sitemap_but_not_the_who_band(): void
    {
        $home = (string) $this->get('/')->assertOk()->getContent();
        foreach (['/for/learners', '/for/singers', '/for/choir-directors?ref=for-choirs-landing'] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $home);
        }

        // And every page's foot leads to them through Who it's for (/for, which links all three).
        foreach (['/pricing', '/for/learners'] as $path) {
            $this->assertStringContainsString('<li><a href="/for" class="s-flink">'.e(__('pages.nav.who_for')).'</a></li>', (string) $this->get($path)->getContent(), $path);
        }
        $hub = (string) $this->get('/for')->assertOk()->getContent();
        foreach (['/for/learners', '/for/singers', '/for/choir-directors'] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $hub);
        }
        $this->assertStringNotContainsString('AI', strip_tags($hub));
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $this->get('/es/for')->assertOk()->assertSee('<html lang="es"', false);

        $sitemap = (string) $this->get('/sitemap.xml')->getContent();
        $this->assertMatchesRegularExpression('#<loc>[^<]*/for</loc>#', $sitemap);
        $this->assertMatchesRegularExpression('#<loc>[^<]*/for/learners</loc>#', $sitemap);
        $this->assertMatchesRegularExpression('#<loc>[^<]*/for/singers</loc>#', $sitemap);
        $this->assertNotContains('/for/learners', array_column(SitePages::who(), 'url'));
        $this->assertNotContains('/for/singers', array_column(SitePages::who(), 'url'));
    }

    /** @param  list<string>  $order */
    private function inOrder(string $html, array $order): void
    {
        $at = -1;
        foreach ($order as $piece) {
            $next = strpos($html, $piece, $at + 1);
            $this->assertNotFalse($next, $piece);
            $this->assertGreaterThan($at, $next, $piece);
            $at = $next;
        }
    }
}
