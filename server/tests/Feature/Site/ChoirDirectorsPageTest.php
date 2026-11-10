<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The choir directors' page (/for/choir-directors), stage 1 of choir learning tracks (docs/choir-tracks-plan.md):
 * one song's learning tracks free, a plain email to Hillel to send it, what singers get and what a director never
 * sees, as questions a search can read too. No price and no plan named until stage 2.
 */
final class ChoirDirectorsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_offers_one_song_free_and_writes_to_hillel(): void
    {
        config(['site.partners_email' => 'partners@example.com']);

        $page = (string) $this->get('/for/choir-directors')->assertOk()->getContent();

        $this->assertStringContainsString(e(__('site.for.pages.choir-directors.lead')), $page);
        $this->assertStringContainsString('href="mailto:partners@example.com?subject=Learning%20tracks"', $page);
        foreach ((array) __('site.for.pages.choir-directors.faq') as [$question]) {
            $this->assertStringContainsString(e($question), $page);
        }
        $this->assertStringContainsString('"@type":"FAQPage"', $page);
        $this->assertStringContainsString('data-choir-board', $page);
    }

    public function test_its_words_name_no_plan_and_no_price(): void
    {
        $words = json_encode(__('site.for.pages.choir-directors'), JSON_THROW_ON_ERROR);

        $this->assertDoesNotMatchRegularExpression('/\b(Plus|Pro)\b|[$€£]\d/', $words);
    }

    public function test_a_director_can_start_a_choir_group_from_here_and_from_choir_groups(): void
    {
        $start = static fn (string $ref): string => 'href="'.e(Hosts::app('/groups?start=1&ref='.$ref)).'"';

        $directors = (string) $this->get('/for/choir-directors')->assertOk()->getContent();
        $this->assertSame(2, substr_count($directors, $start('for-choir-directors')), 'at the top and the end');
        $this->assertStringContainsString(e(__('pages.site.group_start')), $directors);
        // The learning tracks are asked for from the group's page, so the group leads and Write to Hillel follows.
        $this->assertMatchesRegularExpression('#'.preg_quote($start('for-choir-directors'), '#').' class="btn-primary btn-lg">#', $directors);
        $this->assertLessThan(strpos($directors, 'mailto:'), strpos($directors, $start('for-choir-directors')));

        $choirs = (string) $this->get('/features/choirs')->assertOk()->getContent();
        $this->assertSame(2, substr_count($choirs, $start('features-choirs')), 'at the top and the end');
    }

    public function test_the_other_audiences_have_no_questions_section(): void
    {
        $this->get('/for/beginners')->assertOk()->assertDontSee('"@type":"FAQPage"', false);
    }
}
