<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * The courses coming to Plus (docs/plus-courses-plan.md) on the Plus page, below the comparison
 * (Hillel, 2026-10-29: three lists on a band): the section says Coming soon once, at its head, and
 * lists every course, Counterpoint and Vibrato first.
 */
final class PlusCoursesTest extends TestCase
{
    public function test_the_plus_page_lists_the_courses_coming_each_marked_coming_soon(): void
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString(__('pages.pricing.courses_title'), $html);
        $section = substr($html, (int) strpos($html, 'data-plus-courses'));
        $section = substr($section, 0, (int) strpos($section, 'aria-labelledby="end-h"'));
        $this->assertLessThan(strpos($section, 'Vibrato'), strpos($section, 'Counterpoint'));
        $head = substr($section, 0, (int) strpos($section, '<ol'));
        $this->assertStringContainsString(__('pages.nav.coming_soon'), $head, 'the section says Coming soon at its head');
        $courses = array_sum(array_map(static fn (array $group): int => count($group[1]), (array) __('pages.pricing.courses')));
        $this->assertSame($courses, substr_count($section, '<li'), 'every course is listed');
        $this->assertLessThan(strpos($html, 'data-plus-courses'), strpos($html, 'id="compare-h"'), 'the courses come after the comparison');
    }
}
