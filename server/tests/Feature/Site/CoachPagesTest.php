<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Coaching;
use Tests\TestCase;

/**
 * The coach pages in the public site's look: a coach's page shows who they are, what they offer and
 * at what price, and Work with them into the app; the directory lists them once its flag opens.
 */
final class CoachPagesTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    public function test_a_coachs_page_reads_as_a_page_with_their_offers(): void
    {
        [$coach] = $this->coach();

        $page = $this->get('/coach/'.$coach->handle)->assertOk();
        $html = (string) $page->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $page->assertSee('class="s-wrap s-top"', false)->assertSee(__('pages.coach.new'));
        $page->assertSee(__('pages.coach.start', ['name' => $coach->name]));
        $page->assertSee('href="/?coach='.$coach->handle.'&amp;ref=c-'.$coach->handle.'"', false);
        $this->assertStringNotContainsString('<pre>', $html);
        $this->assertMatchesRegularExpression('/[$€£]\d+\.\d\d/', $html, 'the offer shows its price');
    }

    public function test_the_directory_lists_coaches_once_it_opens(): void
    {
        [$coach] = $this->coach();
        $this->get('/coaches')->assertNotFound();

        Flag::query()->create(['name' => 'coach_directory', 'value' => 'on']);
        $this->app->forgetScopedInstances();

        $page = $this->get('/coaches')->assertOk();
        $page->assertSee(__('pages.coaches.headline'))->assertSee('href="/coach/'.$coach->handle.'"', false);
        $this->assertSame(1, substr_count((string) $page->getContent(), '<h1'));
    }
}
