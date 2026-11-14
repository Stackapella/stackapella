<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\CourseClip;
use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Site\SitemapPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The courses' public lesson pages (docs/seo-lesson-pages-plan.md): a page per lesson, or per unit for the courses the
 * courses lead named and the free course; each open course only; an id 301s to its page; never a measure ("Scored on"),
 * never a star; the course's safety line, and belting's daily cap; every page in the sitemap.
 */
final class LessonPagesTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://site.stackapella.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
        Lessons::forget();
    }

    private function open(): void
    {
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();
    }

    public function test_the_free_course_has_a_page_per_unit_and_its_own_page_is_where_harmony_goes(): void
    {
        $pages = Lessons::pages(Lessons::FREE);
        $this->assertCount(8, $pages);
        $this->assertSame('unit', $pages[0]['kind']);

        $this->get(self::SITE.'/courses/harmony')->assertRedirect('/features/course')->assertStatus(301);
        $this->get(self::SITE.'/courses/harmony?ref=share-lesson')->assertRedirect('/features/course?ref=share-lesson')->assertStatus(301);
        $this->get(self::SITE.$pages[1]['url'])->assertOk()->assertSee($pages[1]['name'])->assertSee('"@type":"LearningResource"', false);
    }

    public function test_an_open_course_has_a_page_per_lesson_with_its_challenge_folded_in(): void
    {
        $this->assertArrayNotHasKey('perfect-your-pitch', Lessons::courses(), 'not open until the courses are');
        $this->open();

        $pages = Lessons::pages('perfect-your-pitch');
        $this->assertNotSame([], $pages);
        $this->assertSame('lesson', $pages[0]['kind']);
        foreach ($pages as $page) {
            $this->assertFalse($page['lessons'][0]['challenge'], $page['slug'].': a challenge never stands alone');
        }
        $page = Lessons::page('perfect-your-pitch', 'scoop-or-clean');
        $this->assertNotNull($page);

        $this->get(self::SITE.'/courses/perfect-your-pitch/scoop-or-clean')->assertOk()
            ->assertSee('Scoop or clean')
            ->assertSee('Think the note before you breathe in')
            ->assertSee('https://app.stackapella.test/learn/pp-1-scoop?ref=lesson-page', false)
            ->assertSee('/courses/perfect-your-pitch', false);
        // The lesson's id goes to its page.
        $this->get(self::SITE.'/courses/perfect-your-pitch/pp-1-scoop')->assertStatus(301)->assertRedirect(self::SITE.'/courses/perfect-your-pitch/scoop-or-clean');
        $this->get(self::SITE.'/courses/perfect-your-pitch/no-such-lesson')->assertNotFound();
        // A shared link by a lesson's id keeps its ref (and any query) to the page, so the friend counts.
        $this->get(self::SITE.'/courses/perfect-your-pitch/pp-1-scoop?ref=share-lesson')->assertStatus(301)->assertRedirect(self::SITE.'/courses/perfect-your-pitch/scoop-or-clean?ref=share-lesson');
        $this->get(self::SITE.'/courses/perfect-your-pitch/scoop-or-clean?ref=share-lesson')->assertOk()->assertSee(e('ref=share-lesson'), false);
    }

    public function test_a_lesson_page_retitled_with_its_song_leads_to_its_new_address(): void
    {
        // Backing vocals' Red River Valley lesson became Home on the Range's (2026-11-13); its old page was in the sitemap.
        $this->open();
        $new = self::SITE.'/courses/backing-vocals/home-on-the-range-a-pad-then-answers';
        $this->get($new)->assertOk();
        $this->get(self::SITE.'/courses/backing-vocals/red-river-valley-a-pad-then-answers')->assertStatus(301)->assertRedirect($new);
        $this->assertNotContains('/courses/backing-vocals/red-river-valley-a-pad-then-answers', Lessons::paths());
    }

    public function test_the_thin_courses_have_a_page_per_unit(): void
    {
        $this->open();
        foreach (['learn-your-choir-part'] as $course) {
            $pages = Lessons::pages($course);
            $this->assertNotSame([], $pages, $course);
            $this->assertSame(['unit'], array_values(array_unique(array_column($pages, 'kind'))), $course);
        }
    }

    public function test_no_page_shows_a_measure_or_a_star_and_every_page_says_how_to_sing_safely(): void
    {
        $this->open();
        foreach (Lessons::paths() as $path) {
            $html = (string) $this->get(self::SITE.$path)->assertOk()->getContent();
            $text = strip_tags($html);
            $this->assertStringNotContainsString('Scored on', $text, $path);
            $this->assertDoesNotMatchRegularExpression('/\bstars?\b/i', strip_tags((string) preg_replace('#<script.*?</script>#s', '', $html)), $path);
            $this->assertStringContainsString('data-lesson-safety', $html, $path);
        }
        $belting = Lessons::pages('belting');
        $this->assertNotSame([], $belting);
        $this->get(self::SITE.$belting[0]['url'])->assertSee('no more than 10 minutes a day');
    }

    public function test_a_course_that_is_not_live_has_no_pages(): void
    {
        $this->open();
        $this->assertArrayNotHasKey('other-languages', Lessons::courses());
        $this->get(self::SITE.'/courses/other-languages/anything')->assertNotFound();
    }

    public function test_every_page_has_marketings_title_and_description_and_leads_with_it(): void
    {
        $this->open();
        foreach (array_keys(Lessons::courses()) as $course) {
            foreach (Lessons::pages($course) as $page) {
                $this->assertTrue(Lang::has('lessons.'.$course.'.'.$page['slug'].'.title'), $page['url'].' has no title in lang/en/lessons.php');
                $this->assertIsString($page['description'], $page['url']);
            }
        }
        $page = Lessons::pages(Lessons::FREE)[1];
        $this->get(self::SITE.$page['url'])->assertSee('<title>'.e((string) $page['title']).' | Stackapella</title>', false)->assertSee(e((string) $page['description']), false);
    }

    public function test_a_lesson_plays_its_published_real_voice_and_never_a_clip_still_to_record(): void
    {
        $this->open();
        CourseClip::factory()->create(['slot' => 'vibrato.natural', 'brief' => ['sing' => ['en' => 'A description to never show'], 'used' => ['vib-1-hear']]])
            ->forceFill(['media_key' => 'clips/vibrato-natural.m4a', 'sample_rate' => 48000, 'frames' => 144000, 'published_at' => now()])->save();
        CourseClip::factory()->create(['slot' => 'vibrato.straight', 'brief' => ['sing' => ['en' => 'Still to record, never shown'], 'used' => ['vib-1-hear']]]);

        $slug = Lessons::slugFor('vibrato', 'vib-1-hear');
        $this->assertNotNull($slug);
        $page = $this->get(self::SITE.'/courses/vibrato/'.$slug)->assertOk();
        $page->assertSee('data-lesson-clips', false)->assertSee('clips/vibrato-natural.m4a', false);
        $page->assertDontSee('A description to never show')->assertDontSee('Still to record, never shown');
        $this->assertSame(1, substr_count((string) $page->getContent(), '<audio'));
        // A page whose lessons have no recording has no player.
        $this->get(self::SITE.'/courses/perfect-your-pitch/scoop-or-clean')->assertOk()->assertDontSee('data-lesson-clips', false);
    }

    public function test_every_lesson_page_sings_its_own_lesson_by_a_plain_link_our_referrer_rides_on(): void
    {
        $this->open();
        $pages = Lessons::pages('perfect-your-pitch');
        $later = $pages[count($pages) - 1];
        $own = (string) $later['lessons'][0]['id'];

        // Anyone may sing a lesson once from its page (Hillel, 2026-11-08). The app knows it came from here by
        // ref=lesson-page and our site as the referrer, so the link stays plain and the policy sends our origin.
        $page = $this->get(self::SITE.$later['url'])->assertOk()
            ->assertSee('/learn/'.$own.'?ref=lesson-page', false)
            ->assertSee(__('site.lessons.start'));
        $this->assertSame('strict-origin-when-cross-origin', $page->headers->get('Referrer-Policy'));
        preg_match('#<a [^>]*data-lesson-start[^>]*>#', (string) $page->getContent(), $link);
        $this->assertNotEmpty($link);
        $this->assertStringNotContainsString('noreferrer', $link[0]);
        $this->assertStringNotContainsString('target=', $link[0]);
    }

    public function test_the_course_pages_and_the_beginner_courses_page_start_singing_tagged(): void
    {
        $this->open();
        $first = (string) Lessons::pages('perfect-your-pitch')[0]['lessons'][0]['id'];
        $this->get(self::SITE.'/courses/perfect-your-pitch')->assertOk()
            ->assertSee('/learn/'.$first.'?ref=course-page', false)->assertDontSee('/learn/c/', false);
        $this->get(self::SITE.'/features/course')->assertOk()->assertSee('/learn/1-comfortable?ref=features-course', false);
        // A visitor's own tag wins.
        $this->get(self::SITE.'/features/course?ref=hn')->assertOk()->assertSee('/learn/1-comfortable?ref=hn', false)->assertDontSee('ref=features-course', false);
    }

    public function test_try_one_round_is_dark_until_its_switch_and_then_plays_the_lessons_own_round(): void
    {
        $this->open();
        $url = self::SITE.'/courses/perfect-your-pitch/scoop-or-clean';
        $this->get($url)->assertOk()->assertDontSee('data-lesson-try', false)->assertDontSee('lesson-try.js', false)
            ->assertSee('/learn/pp-1-scoop?ref=lesson-page', false);

        app(Flags::class)->set('lesson_try', 'on', null, 'Trying one round');
        $page = $this->get($url)->assertOk()->assertSee('data-lesson-try', false)->assertSee('/js/public/lesson-try.js', false);
        preg_match('#<script type="application/json" data-lesson-try-round>(.*?)</script>#s', (string) $page->getContent(), $found);
        $round = json_decode($found[1] ?? 'null', true);
        $this->assertIsArray($round);
        $this->assertSame('Scooped or clean?', $round['prompt']);
        $this->assertSame(['Scooped', 'Clean'], $round['options']);
        $this->assertSame(0, $round['answer']);
        $this->assertStringEndsWith('/learn/pp-1-scoop?ref=lesson-try', $round['start']);
        $this->assertNotSame([], $round['clips'][0]['notes']);
        $this->assertArrayNotHasKey('measures', $round);

        // A lesson with no listen-and-choose step has no round.
        $this->get(self::SITE.'/courses/perfect-your-pitch/bullseye')->assertOk()->assertDontSee('data-lesson-try-round', false);

        // The round comes after What you sing, whose steps it plays (the courses lead, 2026-11-11).
        $html = (string) $page->getContent();
        $this->assertLessThan(strpos($html, 'data-lesson-try>'), strpos($html, '>'.__('site.lessons.sing').'</h3>'));
    }

    public function test_a_section_with_no_teaching_keeps_its_steps_under_its_heading_and_home_and_use_steps_show(): void
    {
        $this->open();
        $html = (string) $this->get(self::SITE.'/courses/harmony/find-your-voice')->assertOk()->getContent();

        // "Sing any easy note on "oo"": a home step, now among the steps (Lessons::SUNG).
        $this->assertStringContainsString('Sing any easy note on', $html);
        // The challenge has no teaching: its steps are in the reading column, under its heading.
        preg_match_all('#<div class="s-lesson-read">(.*?)<div class="s-lesson-side">#s', $html, $reads);
        $this->assertNotEmpty(array_filter($reads[1], static fn (string $read): bool => str_contains($read, 'class="s-tool-how"')), 'a reading column holds the challenge\'s steps');
    }

    public function test_sight_singing_has_a_page_per_lesson_and_its_old_unit_pages_lead_to_their_first_lesson(): void
    {
        $this->open();
        $pages = Lessons::pages('sight-singing');
        $this->assertSame(['lesson'], array_values(array_unique(array_column($pages, 'kind'))));
        $this->get(self::SITE.'/courses/sight-singing/find-do-on-the-stave')->assertStatus(301)->assertRedirect(self::SITE.'/courses/sight-singing/do-on-the-stave');
        // Vibrato and Range and registers too (afd0bd985): their old unit pages lead to each unit's first lesson.
        $this->get(self::SITE.'/courses/vibrato/swing-and-settle')->assertStatus(301)->assertRedirect(self::SITE.'/courses/vibrato/swing-your-voice');
        $this->get(self::SITE.'/courses/range-and-registers/mapping-your-break')->assertStatus(301)->assertRedirect(self::SITE.'/courses/range-and-registers/find-your-break');
        $this->assertSame('/courses/range-and-registers/your-easy-range', Lessons::across('/voice-types/alto')[0]['url'], 'the map follows a unit slug to its first lesson');
    }

    public function test_the_guides_and_reference_pages_point_to_their_lessons(): void
    {
        $this->open();
        $guide = Lessons::across('/guides/singing-in-tune');
        $this->assertSame(['/courses/perfect-your-pitch/bullseye', '/courses/perfect-your-pitch/scoop-or-clean', '/courses/perfect-your-pitch/no-sag'], array_column($guide, 'url'));
        $this->assertSame(['/courses/ear-training/four-or-five', '/courses/perfect-your-pitch/the-octave-and-the-fifth'], array_column(Lessons::across('/intervals/perfect-fifth'), 'url'));
        $this->assertSame(['/courses/ear-training/song-starts'], array_column(Lessons::across('/intervals/tritone'), 'url'), 'the rest of the family take its default');
        $this->assertCount(3, Lessons::across('/voice-types/bass'));
        $this->assertCount(3, Lessons::across('/guides/practise-a-choir-part'), "choir-part's two with its slug");
        $this->assertSame([], Lessons::across('/guides/no-such-guide'));

        $this->get(self::SITE.'/intervals/perfect-fifth')->assertOk()->assertSee('/courses/ear-training/four-or-five', false);
        $this->get(self::SITE.'/chords')->assertOk()->assertSee('data-lessons-across', false);
    }

    public function test_a_lessons_unit_pages_are_chips_beside_its_heading_and_a_unit_page_keeps_one_column(): void
    {
        // Hillel's part page redesign (2026-11-10), with the courses lead's conditions: the unit's pages move up as chips,
        // this one current with its "You are here", once on the page; no facts row; a unit page's top stays one column.
        $this->open();
        $html = (string) $this->get(self::SITE.'/courses/perfect-your-pitch/bullseye')->assertOk()->getContent();
        $this->assertStringContainsString('class="s-spart-hero"', $html);
        $this->assertSame(1, substr_count($html, 'id="unit-h"'));
        $this->assertMatchesRegularExpression('#<span class="s-spart-chip" aria-current="page"><b>[^<]+</b><span>'.preg_quote(e(__('site.lessons.here')), '#').'</span></span>#', $html);
        $this->assertGreaterThan(0, substr_count($html, '" class="s-spart-chip">'));
        $this->assertStringNotContainsString('s-spart-facts', $html);
        $this->assertStringContainsString('data-lesson-start', $html);

        $unit = (string) $this->get(self::SITE.'/courses/harmony/find-your-voice')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="s-spart-hero"', $unit);
        $this->assertStringNotContainsString('id="unit-h"', $unit);
        $this->assertStringContainsString('class="s-lesson-cols"', $unit);
    }

    public function test_every_page_is_in_the_sitemap(): void
    {
        $this->open();
        $all = app(SitemapPaths::class)->all();
        foreach (Lessons::paths() as $path) {
            $this->assertContains($path, $all);
        }
    }
}
