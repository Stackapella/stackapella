<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hillel: no app replaces a real vocal coach. The site says so on the landing, in its questions, on
 * the coaching pages and by the Plus courses, and calls working with a coach in the app "soon".
 */
final class CoachLineTest extends TestCase
{
    // It turns coaching on: rolled back after, so no later test finds it on.
    use RefreshDatabase;

    public function test_the_site_says_no_app_replaces_a_coach_and_coaching_in_the_app_is_soon(): void
    {
        $core = e(__('pages.landing.coach_note'));

        $landing = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString($core, $landing);
        $this->assertStringContainsString(e(__('pages.landing.faq.coach.0')), $landing);

        $this->assertStringContainsString($core, (string) $this->get('/features/coaching')->assertOk()->getContent());
        $this->assertStringContainsString(e(__('site.for.pages.coaches.sections.0.heading')), (string) $this->get('/for/coaches')->assertOk()->getContent());
        $pricing = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('pages.pricing.courses_coach')), $pricing);
        // Buying coaching isn't claimed until coaching opens.
        $this->assertStringNotContainsString(e(__('pages.pricing.rows.coaching.0')), $pricing);
        app(Flags::class)->set('coaching', 'on', null, 'Opening coaching');
        $this->assertStringContainsString(e(__('pages.pricing.rows.coaching.0')), (string) $this->get('/pricing')->assertOk()->getContent());
        $this->assertStringContainsString('soon', __('pages.landing.coach_note'));
    }
}
