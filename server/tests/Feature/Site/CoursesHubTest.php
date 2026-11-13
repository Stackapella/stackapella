<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /courses in Hillel's design with filters (2026-11-13): the head counts every course, the chips count each group, every
 * open course offers its first lesson and every other says Soon with none, and the Start here card's units and lessons
 * are the beginner course's own; the filters and search are the page's script, with an empty state ready.
 */
final class CoursesHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('plus_courses', 'on', null, 'test');
    }

    public function test_the_page_counts_and_offers_each_course_as_the_catalogue_has_it(): void
    {
        $html = (string) $this->get('/courses')->assertOk()->getContent();
        $groups = SitePages::courseGroups();
        $total = array_sum(array_map(static fn (array $g): int => count($g['courses']), $groups));

        $this->assertStringContainsString(e(__('site.courses.index.lead_count', ['count' => $total])), $html);
        $this->assertMatchesRegularExpression('#data-filter="all" aria-pressed="true">'.preg_quote(__('pages.site.courses_all'), '#').' <span class="s-crs-n">'.$total.'</span>#', $html);
        foreach ($groups as $key => $group) {
            $this->assertMatchesRegularExpression('#data-filter="'.$key.'" aria-pressed="false">.*?<span class="s-crs-n">'.count($group['courses']).'</span>#s', $html, $key);
            foreach ($group['courses'] as $slug) {
                $card = $this->card($html, $slug);
                if (SitePages::courseOpen($slug)) {
                    // Its first lesson where the app has one (an open course whose lessons aren't paged yet offers none).
                    if (Lessons::firstLesson($slug, 'courses-hub') !== null) {
                        $this->assertStringContainsString('ref=courses-hub', $card, $slug.' offers its first lesson');
                    } else {
                        $this->assertStringNotContainsString('s-crs-try', $card, $slug.' offers no dead link');
                    }
                    $this->assertStringNotContainsString('s-crs-soon', $card);
                } else {
                    $this->assertStringContainsString('s-crs-soon', $card, $slug.' is Soon');
                    $this->assertStringNotContainsString('s-crs-try', $card, $slug.' offers no lesson yet');
                }
            }
        }

        $units = Lessons::pages('harmony');
        $lessons = array_sum(array_map(static fn (array $u): int => count((array) ($u['lessons'] ?? [])), $units));
        $this->assertStringContainsString(e(__('site.courses.index.start_meta', ['units' => count($units), 'lessons' => $lessons])), $html);
        $this->assertStringContainsString('data-courses-empty hidden', $html);
        $this->assertStringContainsString('/js/public/courses-filter.js', $html);
    }

    private function card(string $html, string $slug): string
    {
        $at = strpos($html, 'href="/courses/'.$slug.'"');
        $this->assertNotFalse($at, $slug.' has a card');
        $start = strrpos(substr($html, 0, $at), '<li ');
        $end = strpos($html, '</li>', $at);

        return substr($html, (int) $start, (int) $end - (int) $start);
    }
}
