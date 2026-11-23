<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The courses on /pricing (docs/plus-courses-plan.md): every course free, said in one short band below the comparison
 * (the approved blueprint, 2026-11-23; Saul, the consultant: "radically shorter"), with the way to them all on /courses,
 * which lists each one and which are still to come. The band is about free courses: it never says Coming soon (the
 * coordinator's review, 2026-11-23); /courses says which are still to come.
 */
final class PlusCoursesTest extends TestCase
{
    use RefreshDatabase;

    /** The band's HTML, from its start to the page's closing call. */
    private function section(): string
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'data-plus-courses'), strpos($html, 'id="compare-h"'), 'the courses come after the comparison');
        $section = substr($html, (int) strpos($html, 'data-plus-courses'));

        return substr($section, 0, (int) strpos($section, 'aria-labelledby="close-h"'));
    }

    public function test_the_band_says_every_course_is_free_and_leads_to_them_all(): void
    {
        $section = $this->section();
        $this->assertStringContainsString(e(__('pages.pricing.courses_title')), $section);
        $this->assertStringContainsString('href="/courses"', $section);
        // Free courses, without a Coming soon: /courses says which are still to come.
        $this->assertStringNotContainsString(__('pages.nav.coming_soon'), $section);
        // The courses themselves are /courses's to list.
        $this->assertStringNotContainsString('href="/courses/', $section);
    }

    public function test_once_courses_open_the_band_still_says_no_coming_soon(): void
    {
        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');
        $this->assertGreaterThan(0, collect(SitePages::COURSES)->filter(static fn (string $slug): bool => SitePages::courseOpen($slug))->count(), 'the catalogue has open courses');
        $this->assertStringNotContainsString(__('pages.nav.coming_soon'), $this->section());
    }
}
