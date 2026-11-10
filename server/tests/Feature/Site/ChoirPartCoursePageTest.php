<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Learn your part for rehearsal's page (/courses/learn-your-choir-part), the app's choir-part: its page has a longer
 * slug than the catalogue's id, so the page finds its entry by its own `app` until the catalogue names the slug.
 */
final class ChoirPartCoursePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_open_with_its_lessons_and_leads_into_the_app(): void
    {
        app(Flags::class)->set('plus_courses', 'on', null, 'Courses open');

        $this->assertTrue(SitePages::courseOpen('learn-your-choir-part'));
        $outline = SitePages::courseOutline('learn-your-choir-part');
        $this->assertNotNull($outline);
        $this->assertSame(4, count($outline['units']));

        $page = $this->get('/courses/learn-your-choir-part')->assertOk()
            ->assertSee(__('site.courses.pages.learn-your-choir-part.headline'))
            ->assertSee(__('pages.site.course_open'))
            ->assertSee('?ref=course-page', false);
        foreach ($outline['units'] as $unit) {
            $page->assertSee($unit['title']);
        }

        $this->get('/courses')->assertOk()->assertSee('href="/courses/learn-your-choir-part"', false)->assertDontSee('href="/courses/choir-part"', false);
        $this->get('/for/choir-singers')->assertOk()->assertSee('href="/courses/learn-your-choir-part"', false);
    }

    public function test_its_words_stay_year_round(): void
    {
        $words = json_encode(__('site.courses.pages.learn-your-choir-part'), JSON_THROW_ON_ERROR);

        $this->assertDoesNotMatchRegularExpression('/Christmas|carol|Advent/i', $words);
    }
}
