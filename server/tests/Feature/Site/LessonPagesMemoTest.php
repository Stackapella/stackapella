<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A course's lesson pages (Lessons::pages) are built once a request, never kept past it: a course that opens (the
 * plus_courses switch) shows its first lesson at once. Kept in a static, a long-running process (a parallel test
 * worker, a queue worker) kept a closed course's empty pages, and /courses lost its "Try lesson 1".
 */
final class LessonPagesMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_course_that_opens_offers_its_first_lesson_at_once(): void
    {
        // Closed: no pages, and none offered.
        $this->assertSame([], Lessons::pages('vibrato'));
        $this->assertNull(Lessons::firstLesson('vibrato', 'courses-hub'));

        // The next request, with the course open.
        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');
        $this->app->forgetScopedInstances();
        $this->assertNotNull(Lessons::firstLesson('vibrato', 'courses-hub'), 'its first lesson, not the closed one\'s memory');
        $this->get('/courses')->assertOk()->assertSee(__('site.courses.index.try'));
    }
}
