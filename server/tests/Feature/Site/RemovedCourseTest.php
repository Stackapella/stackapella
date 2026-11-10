<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Courses taken down (Beatboxing, then Vocal percussion, Overtone singing, Character voices and Accents; Hillel, 2026-11-08) leaves no page, no listing and no sitemap entry, and its old
 * address leads to /courses for good.
 */
final class RemovedCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_courses_are_gone_and_their_addresses_lead_to_the_courses(): void
    {
        $courses = (string) $this->get('/courses')->assertOk()->getContent();
        $sitemap = (string) $this->get('/sitemap.xml')->getContent();

        foreach (['beatboxing', 'vocal-percussion', 'overtone-singing', 'character-voices', 'accents'] as $slug) {
            $this->get('/courses/'.$slug)->assertStatus(301)->assertRedirect(SitePages::REMOVED_COURSES[$slug]);
            $this->assertNotContains($slug, SitePages::COURSES);
            $this->assertNotContains('/courses/'.$slug, SitePages::paths());
            $this->assertStringNotContainsString('href="/courses/'.$slug.'"', $courses);
            $this->assertStringNotContainsString('/courses/'.$slug.'<', $sitemap);
            foreach (SitePages::courseGroups() as $group) {
                $this->assertNotContains($slug, $group['courses']);
            }
        }
        // Vocal percussion's leads to its guide, which answers what the course was found for.
        $this->get('/courses/vocal-percussion')->assertRedirect('/guides/vocal-percussion');
        $this->get('/guides/vocal-percussion')->assertOk()->assertSee('href="/tools/metronome"', false)->assertSee('href="/guides/arranging-a-cappella"', false);
        // A shared link to a removed course keeps its ref to where it lands.
        $this->get('/courses/beatboxing?ref=share-lesson')->assertStatus(301)->assertRedirect('/courses?ref=share-lesson');
        // What stays: whistling and live looping, the styles beside them.
        $this->assertStringContainsString('href="/courses/live-looping"', $courses);
    }
}
