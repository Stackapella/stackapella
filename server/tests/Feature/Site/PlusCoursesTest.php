<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Tests\TestCase;

/**
 * The courses coming to Plus (docs/plus-courses-plan.md) on the Plus page, below the comparison
 * (Hillel, 2026-10-29: three lists on a band): the section says Coming soon once, at its head, and
 * lists every course in the groups /courses has.
 */
final class PlusCoursesTest extends TestCase
{
    public function test_the_plus_page_lists_the_courses_coming_each_marked_coming_soon(): void
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString(__('pages.pricing.courses_title'), $html);
        $section = substr($html, (int) strpos($html, 'data-plus-courses'));
        $section = substr($section, 0, (int) strpos($section, 'aria-labelledby="end-h"'));
        // The groups as /courses has them, in their order (SitePages::courseGroups).
        $groups = SitePages::courseGroups();
        $names = array_column($groups, 'name');
        $this->assertLessThan(strpos($section, e($names[1])), strpos($section, e($names[0])));
        $head = substr($section, 0, (int) strpos($section, '<ol'));
        $this->assertStringContainsString(__('pages.nav.coming_soon'), $head, 'the section says Coming soon at its head');
        $courses = array_sum(array_map(static fn (array $group): int => count($group['courses']), $groups));
        $this->assertSame($courses, substr_count($section, '<li'), 'every course is listed');
        $this->assertLessThan(strpos($html, 'data-plus-courses'), strpos($html, 'id="compare-h"'), 'the courses come after the comparison');
    }
}
