<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Launch day flips switches, not words (docs/launch-plan.md): a Plus course opens with the
 * plus_courses flag and its status in web/course/courses.json (and the plans then list the Plus
 * courses), and Hum a tune's page takes its
 * present-tense words once it's live, with sharing as an idea still coming, and its help section
 * appears with it.
 */
final class LaunchSwitchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_live_course_opens_with_the_plus_courses_flag_and_the_others_stay_coming(): void
    {
        $this->get('/courses/vibrato')->assertOk()
            ->assertSee(__('pages.site.course_soon'))
            ->assertDontSee(__('pages.site.course_try'));

        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');

        $this->get('/courses/vibrato')->assertOk()
            ->assertSee((string) __('site.courses.pages.vibrato.close'))
            ->assertSee(__('pages.site.course_try'))
            ->assertSee('/learn/c/vibrato', false);
        $this->get('/courses/'.$this->aCourseStillComing())->assertOk()
            ->assertSee(__('pages.site.course_soon'))
            ->assertDontSee(__('pages.site.course_try'));
        $this->get('/courses')->assertOk()->assertSee(__('pages.site.course_open'));
    }

    public function test_the_plans_list_the_plus_courses_once_they_open(): void
    {
        $this->get('/')->assertOk()->assertDontSee(__('pages.landing.plans_plus_courses'));
        $this->get('/pricing')->assertOk()->assertDontSee(__('pages.landing.plans_plus_courses'));

        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');

        $this->get('/')->assertOk()->assertSee(__('pages.landing.plans_plus_courses'));
        $this->get('/pricing')->assertOk()->assertSee(__('pages.landing.plans_plus_courses'));
    }

    public function test_the_coaches_page_leads_to_applying_once_coach_sign_up_opens(): void
    {
        $this->get('/for/coaches')->assertOk()
            ->assertSee('mailto:', false)
            ->assertDontSee(__('pages.site.coach_apply'));

        app(Flags::class)->set('coach_signup', 'on', null, 'Opening coach sign-up');

        $this->get('/for/coaches')->assertOk()
            ->assertSee(__('pages.site.coach_apply'))
            ->assertSee('/coach/apply', false)
            ->assertSee(e(__('pages.site.coach_signup_line')), false)
            ->assertDontSee('mailto:'.config('site.contact_email').'" class="btn-primary', false);
    }

    public function test_hum_a_tune_takes_its_open_words_once_live_with_ideas_still_coming(): void
    {
        $this->get('/features/your-tune')->assertOk()->assertSee(e(__('site.features.pages.your-tune.lead')), false);

        app(Flags::class)->set('your_tune', 'on', null, 'Hum a tune opens');
        $this->app->forgetScopedInstances();

        $page = $this->get('/features/your-tune')->assertOk();
        $page->assertSee(e(__('site.features.pages.your-tune.now.lead')), false)
            ->assertSee(e(__('site.features.pages.your-tune.now.sections.Hear it sung by real people.soon')), false)
            ->assertDontSee(e(__('site.features.pages.your-tune.sections.3.heading')), false);
    }

    /** A course the catalogue still lists as coming, so the test holds as courses go live. */
    public function test_the_help_explains_hum_a_tune_only_once_its_switch_is_on(): void
    {
        $this->get('/help/create')->assertOk()
            ->assertDontSee('Hum a tune')
            ->assertDontSee('id="hum-a-tune"', false)
            ->assertSee('Start from chords');

        app(Flags::class)->set('your_tune', 'on', null, 'Opening Hum a tune');

        $this->get('/help/create')->assertOk()
            ->assertSee('id="hum-a-tune"', false)
            ->assertSee('"Start from chords", "Hum a tune"')
            ->assertSee('Start humming');
    }

    private function aCourseStillComing(): string
    {
        $catalogue = json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/courses.json'), true);
        foreach ($catalogue['courses'] ?? [] as $course) {
            if (($course['status'] ?? '') === 'soon') {
                return (string) ($course['slug'] ?? $course['id']);
            }
        }
        $this->fail('The catalogue has no course still coming.');
    }
}
