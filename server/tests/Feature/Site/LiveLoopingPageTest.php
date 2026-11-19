<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Site\LiveLoopingPage;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Live looping course page (Hillel's design, 2026-11-18): its loop rings are the course's own Are You Sleeping
 * (Frère Jacques) tracks, every lesson block links to the page that teaches it, and its take says it's a sample.
 */
final class LiveLoopingPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The courses open, so Live looping has its lesson pages.
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();
    }

    public function test_the_rings_are_the_courses_own_frere_jacques(): void
    {
        $loop = LiveLoopingPage::rings();
        $rings = array_column($loop['rings'], null, 'key');

        $this->assertSame(96, $loop['tempo']);
        $this->assertSame(['bass', 'pad', 'tune', 'round'], array_keys($rings));
        // The bass is "ding, dang, dong": do, low so, do, each bar.
        $this->assertSame([[0.0, 1.0, 60], [1.0, 1.0, 55], [2.0, 1.5, 60]], array_map(static fn (array $n): array => [$n['at'], $n['len'], $n['midi']], $rings['bass']['notes']));
        // The pad is a held "oo" on so.
        $this->assertSame([[0.0, 3.5, 67]], array_map(static fn (array $n): array => [$n['at'], $n['len'], $n['midi']], $rings['pad']['notes']));
        // The tune is the whole round, and the round is the same tune two bars after it.
        $this->assertCount(32, $rings['tune']['notes']);
        $this->assertSame($rings['tune']['notes'], $rings['round']['notes']);
        $this->assertSame(8, $rings['round']['from'] - $rings['tune']['from']);

        // One turn shows every note once: 8 bars of the bass and the pad, and the tune once each.
        $this->assertCount(24, $rings['bass']['arcs']);
        $this->assertCount(8, $rings['pad']['arcs']);
        $this->assertCount(32, $rings['round']['arcs']);
        // The tune joins at beat 8, so its first note sits a quarter of the way round, and the round's halfway.
        $this->assertContains(8.0, array_column($rings['tune']['arcs'], 'pos'));
        $this->assertContains(16.0, array_column($rings['round']['arcs'], 'pos'));
        foreach ($rings as $key => $ring) {
            foreach ($ring['arcs'] as $arc) {
                $this->assertLessThanOrEqual(32.0, $arc['pos'] + $arc['len'], "{$key} at {$arc['pos']} stays inside one turn");
            }
        }
    }

    public function test_every_lesson_block_links_to_the_page_that_teaches_it(): void
    {
        $outline = SitePages::courseOutline('live-looping');
        $this->assertNotNull($outline);
        $units = LiveLoopingPage::units('live-looping', $outline);
        $lessons = array_merge(...array_column($units, 'lessons'));

        $this->assertCount($outline['lessons'], $lessons);
        foreach ($lessons as $lesson) {
            $this->assertNotNull($lesson['url'], "{$lesson['title']} links to its page");
        }
        $byTitle = array_column($lessons, 'url', 'title');
        // A lesson taught on another's page links there.
        $this->assertSame('/courses/live-looping/a-longer-loop', $byTitle['Your first loop']);
        $this->assertSame('/courses/live-looping/choose-your-layers', $byTitle['Your song']);
        $this->assertSame('1.1 · Start here', $lessons[0]['tag']);
        $this->assertSame('4.3 · All tracks', end($lessons)['tag']);
    }

    public function test_the_science_and_the_story_are_the_courses_own_facts_with_their_sources(): void
    {
        $facts = LiveLoopingPage::facts();
        $sources = LiveLoopingPage::sources();

        // Every lesson fact of the course is on the page, once, and each has its source.
        $cited = [...LiveLoopingPage::SCIENCE, ...array_merge(...LiveLoopingPage::STORY)];
        $this->assertCount(11, $facts);
        $this->assertEqualsCanonicalizing(array_keys($facts), $cited);
        $this->assertSame($cited, array_keys($sources));

        $html = (string) $this->get('/courses/live-looping')->assertOk()->getContent();
        foreach ($facts as $fact) {
            $this->assertStringContainsString(e($fact['text']), $html);
            $this->assertStringContainsString('href="'.e($fact['url']).'"', $html);
        }
        $this->assertStringContainsString('id="src-11"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);

        // The first lesson to try, as the app teaches it.
        $first = LiveLoopingPage::firstLesson();
        $this->assertCount(3, $first['cards']);
        $this->assertCount(2, $first['steps']);
        $this->assertStringContainsString(e($first['steps'][0]), $html);
    }

    public function test_the_page_draws_the_loop_and_marks_the_take_a_sample(): void
    {
        $html = (string) $this->get('/courses/live-looping')->assertOk()->getContent();

        $this->assertStringContainsString('data-ll ', $html);
        $this->assertSame(96, substr_count($html, 'class="cp-ll-arc"'));
        $this->assertStringContainsString('A sample take', $html);
        $this->assertStringContainsString('/js/public/live-looping.js', $html);
        // The beginner course's real map in the app, never a placeholder.
        $this->assertStringContainsString('your-voice--map', $html);
        // Every lesson page is linked from the page's own list.
        $this->assertNotSame([], Lessons::pages('live-looping'));
        foreach (Lessons::pages('live-looping') as $page) {
            $this->assertStringContainsString('href="'.$page['url'].'"', $html);
        }
        // Every fact the page had: the songs with their credits and the safety lines.
        foreach (['Traditional, 18th century, public domain', 'Sea shanty, traditional, public domain', 'Written for the course', 'Easy volume, in your middle notes, with the bass light.', 'Stop if anything tightens or hurts.'] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
    }
}
