<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\BreathAndStaminaPage;
use App\Support\Pages\Lessons;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Breath and stamina course page (site/courses/breath-and-stamina, Hillel's design of 2026-11-18): its lesson map
 * links every lesson to its page, a challenge to its place on the page it's folded into; the map's count line takes the
 * catalogue's minutes; the sample result says it's a sample; and the quiz's letters stay A and B in Spanish.
 */
final class BreathAndStaminaPageTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://site.stackapella.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();
    }

    public function test_the_map_links_every_lesson_and_each_challenge_to_its_place(): void
    {
        $page = (array) __('site.courses.pages.breath-and-stamina');
        $outline = SitePages::courseOutline('breath-and-stamina');
        $this->assertNotNull($outline);
        $units = BreathAndStaminaPage::units($page, $outline);

        $lessons = array_merge(...array_map(static fn (array $unit): array => $unit['lessons'], $units));
        $this->assertCount($outline['lessons'], $lessons);
        foreach ($lessons as $lesson) {
            $this->assertNotNull($lesson['url']);
            $this->assertStringStartsWith('/courses/breath-and-stamina/', (string) $lesson['url']);
            $this->assertNotSame('', $lesson['line'], $lesson['name'].' has its line');
        }
        $easyHold = collect($lessons)->firstWhere('name', 'The easy hold');
        $this->assertNotNull($easyHold);
        $this->assertSame('/courses/breath-and-stamina/a-long-line#lesson-2', $easyHold['url']);

        $html = $this->get(self::SITE.'/courses/breath-and-stamina')->assertOk()
            ->assertSee(BreathAndStaminaPage::mapMeta($page, $outline))
            ->assertSee($page['sample']['tag'])
            ->assertSee($page['sample']['label'])
            ->getContent();
        foreach ($lessons as $lesson) {
            $this->assertStringContainsString('href="'.e((string) $lesson['url']).'"', (string) $html);
        }
    }

    public function test_the_count_line_says_the_catalogues_minutes_and_leaves_them_out_without_them(): void
    {
        $page = (array) __('site.courses.pages.breath-and-stamina');
        $outline = ['units' => [['title' => 'One', 'lessons' => ['a']], ['title' => 'Two', 'lessons' => ['b']]], 'lessons' => 2];

        $this->assertSame('2 units · 2 lessons · about 50 min', BreathAndStaminaPage::mapMeta($page, [...$outline, 'minutes' => 50]));
        $this->assertSame(__('pages.site.course_size', ['units' => 2, 'lessons' => 2]), BreathAndStaminaPage::mapMeta($page, [...$outline, 'minutes' => null]));
    }

    public function test_spanish_keeps_the_quiz_letters_and_writes_its_decimals_its_own_way(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $this->get(self::SITE.'/es/courses/breath-and-stamina')->assertOk()
            ->assertSee('data-br-pick="a" translate="no">A<', false)
            ->assertSee('data-br-pick="b" translate="no">B<', false)
            ->assertSee('data-br-held-count>9,4<', false);
    }
}
