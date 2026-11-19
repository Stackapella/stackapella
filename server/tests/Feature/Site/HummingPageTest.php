<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Site\HummingRibbons;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Humming and lip trills course page (Hillel's design, 2026-11-18): its ribbons are the lessons' own exercises, every
 * lesson is a link to the page it's on, each "Did you know?" fact is cited from the lesson that tells it, and the take is
 * marked as a sample.
 */
final class HummingPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The courses open, so the course has its lesson pages (Lessons::courses).
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();
    }

    protected function tearDown(): void
    {
        Lessons::forget();
        parent::tearDown();
    }

    public function test_the_hero_ribbon_is_the_lessons_own_four_sounds(): void
    {
        $hero = HummingRibbons::hero();
        $sing = json_decode($hero['sing'], true);

        $this->assertSame(['trill', 'siren', 'hum', 'ah'], array_column($hero['segments'], 'kind'));
        $this->assertSame([1, 2, 3, 4], array_column($hero['segments'], 'part'));
        $this->assertSame(HummingRibbons::BASE, $sing['base']);
        // A roller coaster's ribbon (6 s), Bigger sirens' first (5 s), A buzzing hum's held note (4 beats at 60) and Hum
        // to open's "ah" (2 beats at 76), one after another.
        $this->assertSame([6.0, 5.0, 4.0, 1.579], array_map('floatval', array_column($sing['sounds'], 'len')));
        $this->assertSame([0.0, 6.0, 11.0, 15.0], array_map('floatval', array_column($sing['sounds'], 'at')));
        $peaks = array_map(static fn (array $s): float => max([0.0, ...array_column($s['bend'], 1)]), $sing['sounds']);
        $this->assertEqualsWithDelta([7.0, 9.0, 0.0, 0.0], $peaks, 0.05, 'the coaster tops at 7, the siren at 9, the hum and "ah" hold their note');
        foreach ($sing['sounds'] as $sound) {
            $this->assertEqualsWithDelta(0.0, $sound['bend'][0][1], 0.001, "{$sound['kind']} starts on the singer's note");
            $this->assertEqualsWithDelta(0.0, end($sound['bend'])[1], 0.001, "{$sound['kind']} ends on it");
        }
        foreach ($hero['segments'] as $segment) {
            $this->assertNotSame('', $segment['line'].$segment['fill'], "{$segment['kind']} is drawn");
        }
        $this->assertNotSame('', $hero['segments'][0]['dots'], 'the trill has its bubbles');
    }

    public function test_every_lesson_in_the_units_links_to_the_page_it_is_on(): void
    {
        $outline = SitePages::courseOutline('humming-and-lip-trills');
        $this->assertNotNull($outline);
        $units = HummingRibbons::units($outline, (array) __('site.courses.pages.humming-and-lip-trills.learn'));
        $pages = array_column(Lessons::pages('humming-and-lip-trills'), 'url');

        $this->assertCount(4, $units);
        $this->assertSame('A roller coaster on one bubble, keeping it bubbling, and a trill on a tune.', $units[0]['learn']);
        $links = 0;
        foreach ($units as $unit) {
            foreach ($unit['lessons'] as $lesson) {
                $this->assertContains($lesson['url'], $pages, "{$lesson['name']} links to a lesson page");
                $links++;
            }
        }
        $this->assertSame($outline['lessons'], $links);
        // A challenge is on its unit's third lesson's page.
        $this->assertSame('/courses/humming-and-lip-trills/trill-on-a-tune', $units[0]['lessons'][3]['url']);
        $this->assertTrue($units[0]['lessons'][0]['start']);
    }

    public function test_the_headline_marks_its_coloured_words_in_any_language(): void
    {
        $this->assertSame([
            ['text' => 'Wake your voice up gently: ', 'part' => null],
            ['text' => 'bubble', 'part' => 1],
            ['text' => ', ', 'part' => null],
            ['text' => 'hum', 'part' => 3],
            ['text' => ', then ', 'part' => null],
            ['text' => 'sing.', 'part' => 4],
        ], HummingRibbons::marked('Wake your voice up gently: [1 bubble], [3 hum], then [4 sing.]'));

        $html = (string) $this->get('/es/courses/humming-and-lip-trills')->getContent();
        if (str_contains($html, 'cp-hum')) {
            $this->assertStringContainsString('<span class="cp-hum-em s-v1">vibra los labios</span>', $html);
        }
    }

    public function test_the_page_cites_its_facts_links_every_lesson_and_marks_the_take_a_sample(): void
    {
        $html = (string) $this->get('/courses/humming-and-lip-trills')->assertOk()->getContent();

        // The three facts, each with its big word and a numbered note to its lesson's source.
        foreach (['bocca chiusa', 'ngā', 'm → ah'] as $word) {
            $this->assertStringContainsString(e($word), $html);
        }
        // Every one of the course's twelve checked lesson facts, each with its numbered note and its source listed once.
        $this->assertSame(12, substr_count($html, 'class="s-cite"'));
        $this->assertSame(12, preg_match_all('/<li id="src-\d+">/', $html));
        foreach (['https://www.rsmgb.org/news/from-the-archives-giacomo-puccini', 'https://maoridictionary.co.nz/word/4504', 'https://archive.org/details/selectedwritings01jako'] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $html);
        }
        // Every lesson page, linked from the page's own list.
        foreach (Lessons::pages('humming-and-lip-trills') as $page) {
            $this->assertStringContainsString('href="'.$page['url'].'"', $html);
        }
        $this->assertStringContainsString('21 of 24 notes on pitch', $html);
        $this->assertStringContainsString('>Sample</b>', $html);
        $this->assertStringContainsString('/js/public/humming-and-lip-trills.js', $html);
        // Every fact the page had: its safety lines.
        foreach (['Keep every sound quiet to medium and easy, never forced, and stop if anything tightens, tickles or hurts.', 'Can\'t trill yet? A hum or an "oo" does the same job here.'] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
    }

    public function test_the_science_story_and_first_lesson_are_the_courses_own_words_and_facts(): void
    {
        $science = HummingRibbons::science();
        $this->assertSame(['trill', 'siren', 'hum', 'ah'], array_column($science, 'kind'));
        $this->assertStringStartsWith('A lip trill, or lip bubble, is a "brr"', $science[0]['lead']);
        foreach ($science as $card) {
            $this->assertNotSame('', $card['fact'], "{$card['kind']} has its fact");
        }

        // The dated story, oldest first, each year said in its own fact.
        $story = HummingRibbons::story();
        $years = array_column($story, 'year');
        $this->assertSame([1819, 2002, 2014, 2018, 2020], $years);
        foreach ($story as $stop) {
            $this->assertStringContainsString((string) $stop['year'], $stop['text']);
        }

        // Lesson 1 as the app gives it: its three learn cards, then what it asks you to sing.
        $try = HummingRibbons::tryIt();
        $this->assertSame('A roller coaster', $try['name']);
        $this->assertSame(['Loose lips', 'Easy breath', 'Stop if it strains', ''], array_column($try['steps'], 'title'));
        $this->assertStringStartsWith('Ride the ribbon\'s roller coaster', $try['steps'][3]['text']);
        $this->assertNotSame('', $try['dots']);

        $html = (string) $this->get('/courses/humming-and-lip-trills')->assertOk()->getContent();
        foreach ((array) __('site.courses.pages.humming-and-lip-trills.faq') as [$question]) {
            $this->assertStringContainsString(e($question), $html);
        }
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
    }
}
