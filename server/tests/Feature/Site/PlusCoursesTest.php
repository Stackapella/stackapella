<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The courses on Plus (docs/plus-courses-plan.md) on the Plus page, below the comparison (Hillel,
 * 2026-10-29: three lists on a band), as the app's More courses list has them: each group's open
 * courses as rows linked to their pages, the ones still to come in one quiet line. Before any opens,
 * the section says Coming soon once, at its head, and the lines are the names alone.
 */
final class PlusCoursesTest extends TestCase
{
    use RefreshDatabase;

    /** The section's HTML, from its band to the page's closing call. */
    private function section(): string
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'data-plus-courses'), strpos($html, 'id="compare-h"'), 'the courses come after the comparison');
        $section = substr($html, (int) strpos($html, 'data-plus-courses'));

        return substr($section, 0, (int) strpos($section, 'aria-labelledby="end-h"'));
    }

    public function test_before_any_course_opens_the_page_says_coming_soon_once_and_names_every_course(): void
    {
        $section = $this->section();
        $this->assertStringContainsString(__('pages.pricing.courses_title'), $section);
        $groups = SitePages::courseGroups();
        $names = array_column($groups, 'name');
        // The groups as /courses has them, in their order (SitePages::courseGroups).
        $this->assertLessThan(strpos($section, e($names[1])), strpos($section, e($names[0])));
        $this->assertSame(1, substr_count($section, __('pages.nav.coming_soon')), 'Coming soon once, at the head');
        $this->assertStringNotContainsString('data-courses-open', $section, 'no course open yet');
        foreach ($groups as $group) {
            foreach ($group['courses'] as $slug) {
                $this->assertStringContainsString(e(__('site.courses.pages.'.$slug.'.name')), $section, "{$slug} is named");
            }
        }
    }

    public function test_once_courses_open_each_is_a_row_linked_to_its_page_and_the_rest_wait_in_a_line(): void
    {
        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');
        $section = $this->section();
        $open = 0;
        foreach (SitePages::courseGroups() as $group) {
            foreach ($group['courses'] as $slug) {
                if (SitePages::courseOpen($slug)) {
                    $open++;
                    $this->assertStringContainsString('href="/courses/'.$slug.'"', $section, "{$slug} links to its page");
                } else {
                    $this->assertStringNotContainsString('href="/courses/'.$slug.'"', $section, "{$slug} is still to come: named, not linked");
                    $this->assertStringContainsString(e(__('site.courses.pages.'.$slug.'.name')), $section, "{$slug} is named");
                }
            }
        }
        $this->assertGreaterThan(0, $open, 'the catalogue has open courses to show');
        $this->assertSame($open, substr_count($section, '<li'), 'a row for each open course');
    }
}
