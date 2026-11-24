<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Http\Hosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * For choir directors (/for/choir-directors), radically shorter (Hillel, 2026-11-23, from the consultant's concept): the
 * promise beside the director's view as a live demo, one way in with the email beside it, the four steps, "No group
 * yet?" with the part pages, three questions a search can read too, and the last word. Its words follow the live
 * switches through Teams (a seat per singer while choir seats are on).
 */
final class ChoirDirectorsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_in_its_order_with_the_directors_view_beside_the_promise(): void
    {
        config(['site.partners_email' => 'partners@example.com']);

        $page = (string) $this->get('/for/choir-directors')->assertOk()->getContent();

        $order = [
            '<h1 id="page-h">'.e(__('site.for_directors.h1')).'</h1>',
            e(__('site.for_directors.headline')),
            'data-choir-board',
            e(__('site.for_directors.steps_title')),
            e(__('site.for_directors.nogroup.title')),
            'id="faq-h"',
            e(__('site.for_directors.end.title')),
        ];
        $at = -1;
        foreach ($order as $words) {
            $next = strpos($page, $words);
            $this->assertNotFalse($next, $words);
            $this->assertGreaterThan($at, $next, $words);
            $at = $next;
        }
        // The board's caption says the privacy once; the board's own privacy line stays out.
        $this->assertStringContainsString(e(__('site.for_directors.view.caption')), $page);
        $this->assertStringNotContainsString(e(__('pages.site.choir_demo.private')), $page);
        $this->assertStringContainsString(e(__('site.for_directors.view.demo')), $page);
        $this->assertStringContainsString('href="mailto:partners@example.com?subject=Learning%20tracks"', $page);
        $this->assertSame(4, substr_count($page, '<h3>'));
        $this->assertStringContainsString('href="/songs"', $page);
    }

    public function test_four_questions_also_as_structured_data(): void
    {
        $page = (string) $this->get('/for/choir-directors')->assertOk()->getContent();

        $faq = (array) __('site.for_directors.faq');
        // Three about the tracks, and one for every title a choir's leader goes by (marketing, 2026-11-23).
        $this->assertCount(4, $faq);
        foreach ($faq as [$question]) {
            $this->assertStringContainsString(e($question), $page);
        }
        $this->assertSame(1, substr_count($page, '"@type":"FAQPage"'));
    }

    public function test_a_director_starts_a_choir_group_at_the_top_and_the_end(): void
    {
        $start = static fn (string $ref): string => 'href="'.e(Hosts::app('/groups?start=1&ref='.$ref)).'"';

        $directors = (string) $this->get('/for/choir-directors')->assertOk()->getContent();
        $this->assertSame(2, substr_count($directors, $start('for-choir-directors')), 'at the top and the end');
        $this->assertMatchesRegularExpression('#'.preg_quote($start('for-choir-directors'), '#').' class="btn-primary btn-lg">#', $directors);
        $this->assertLessThan(strpos($directors, 'mailto:'), strpos($directors, $start('for-choir-directors')));

        $choirs = (string) $this->get('/features/choirs')->assertOk()->getContent();
        $this->assertSame(2, substr_count($choirs, $start('features-choirs')), 'at the top and the end');
    }

    public function test_with_choir_seats_on_it_says_a_seat_per_singer_not_join_free(): void
    {
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        $this->assertStringContainsString('Starting a group takes Plus. Your singers join free.', (string) $this->get('/for/choir-directors')->assertOk()->getContent());

        app(Flags::class)->set('choir_seats', 'on', null, 'test');
        $page = (string) $this->get('/for/choir-directors')->assertOk()->getContent();
        $this->assertStringNotContainsString('join free', $page);
        $this->assertStringContainsString('Each singer needs a seat, which you buy for the choir from 10 seats', $page);
    }

    public function test_the_other_audiences_have_no_questions_section(): void
    {
        $this->get('/for/beginners')->assertOk()->assertDontSee('"@type":"FAQPage"', false);
    }
}
