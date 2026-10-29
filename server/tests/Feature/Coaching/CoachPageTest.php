<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Models\CoachProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Coaching;
use Tests\TestCase;

final class CoachPageTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    public function test_an_approved_coachs_page_shows_their_profile_with_no_cookies_and_never_indexed(): void
    {
        [$coach] = $this->coach();

        $page = $this->get('/coach/'.$coach->handle);

        $page->assertOk()->assertViewIs('public.coach')->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertSame([], $page->headers->getCookies());
        $page->assertViewHas('coach', fn (array $view): bool => $view['handle'] === $coach->handle && $view['newCoach'] === true && count($view['offerings']) === 1);
        $page->assertViewHas('links', ['app' => '/?coach='.$coach->handle.'&ref=c-'.$coach->handle]);
        $page->assertViewHas('meta', fn (array $meta): bool => str_contains($meta['title'], 'Grace Okafor'));
    }

    public function test_anyone_else_is_the_gone_page(): void
    {
        [$coach] = $this->coach();
        CoachProfile::query()->where('user_id', $coach->id)->update(['status' => CoachProfile::SUSPENDED]);

        $this->get('/coach/'.$coach->handle)->assertNotFound()->assertViewIs('public.coach-gone')->assertViewHas('links', ['app' => '/']);
        $this->get('/coach/nobody_here')->assertNotFound()->assertViewIs('public.coach-gone');
    }
}
