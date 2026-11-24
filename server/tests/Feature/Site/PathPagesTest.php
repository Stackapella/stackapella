<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Flags;
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
            '<h1 id="page-h">'.e(__('site.for.pages.learners.h1')).'</h1>',
            '/learn/1-comfortable?ref=for-learners',
            e(__('site.for.pages.learners.heads.hear')),
            'href="/songs/amazing-grace"',
            'href="/guides/what-is-harmony"',
            e(__('site.for.pages.learners.heads.sing')),
            'href="/features/course"',
            'href="/courses/perfect-your-pitch"',
            'href="/tools/vocal-range-test"',
            'href="/guides/sing-harmony-with-yourself"',
            e(__('site.for.pages.learners.heads.better')),
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
            '<h1 id="page-h">'.e(__('site.for.pages.singers.h1')).'</h1>',
            '/t/amazing-grace?ref=for-singers',
            e(__('site.for.pages.singers.heads.pick')),
            'href="/songs"',
            e(__('site.for.pages.singers.heads.record')),
            'href="/features/studio"',
            'href="/courses/sight-singing"',
            e(__('site.for.pages.singers.heads.mix')),
            'href="/tools/audio-latency-test"',
            'href="/guides/record-vocal-harmonies-at-home"',
            e(__('site.for.pages.singers.heads.share')),
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
        $this->get('/es/for/learners')->assertSee('<html lang="es"', false)->assertSee('<h1 id="page-h">Para aprendices</h1>', false);
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

        // And every page's foot, under Who it's for.
        foreach (['/pricing', '/for/learners'] as $path) {
            $foot = (string) $this->get($path)->getContent();
            $this->assertStringContainsString('<li><a href="/for/learners" class="s-flink">'.e(__('pages.nav.learners')).'</a></li>', $foot, $path);
            $this->assertStringContainsString('<li><a href="/for/singers" class="s-flink">'.e(__('pages.nav.singers')).'</a></li>', $foot, $path);
        }

        $sitemap = (string) $this->get('/sitemap.xml')->getContent();
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
