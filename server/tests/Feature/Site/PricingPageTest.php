<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /pricing in Hillel's 2026-11-12 design: each card's button under its price, a trial named only when it's real
 * (checkout open and trial days set), Plus's way in at the comparison's foot only once checkout is open, and "Only
 * what's different" folding the rows both plans share.
 */
final class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    private function open(int $trialDays): void
    {
        config(['billing.prices.month' => 'price_month', 'billing.prices.year' => 'price_year', 'billing.trial_days' => $trialDays]);
    }

    public function test_while_checkout_is_closed_plus_says_so_and_no_trial_or_cancel_line_shows(): void
    {
        config(['billing.prices.month' => null, 'billing.prices.year' => null]);
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString(e(__('pages.landing.plans_paused')), $html);
        $this->assertStringNotContainsString(e(trans_choice('pages.pricing.try_plus', 7, ['days' => 7])), $html);
        $this->assertStringNotContainsString(e(__('pages.pricing.cancel_any_time')), $html);
        $this->assertStringNotContainsString('from=pricing-table', $html);
        // Free's Start singing is the page's primary while it's the only thing to do.
        $this->assertMatchesRegularExpression('/class="btn-primary btn-lg" data-funnel-start/', $html);
    }

    public function test_once_checkout_opens_the_trial_button_leads_and_the_table_ends_with_get_plus(): void
    {
        $this->open(7);
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString(e(trans_choice('pages.pricing.try_plus', 7, ['days' => 7])), $html);
        $this->assertStringContainsString(e(__('pages.pricing.cancel_any_time')), $html);
        $this->assertStringContainsString('from=pricing-table', $html);
        $this->assertStringNotContainsString(e(__('pages.landing.plans_paused')), $html);
        $this->assertMatchesRegularExpression('/class="btn-secondary btn-lg" data-funnel-start/', $html);
    }

    public function test_with_no_trial_days_the_button_never_names_a_trial(): void
    {
        $this->open(0);
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringNotContainsString('Try Plus free', $html);
        $this->assertStringContainsString('>'.e(__('pages.landing.plans_get')).'</a>', $html);
        $this->assertStringNotContainsString(e(__('pages.pricing.faq.1.0')), $html, 'no trial question without a trial');
    }

    public function test_only_whats_different_marks_the_rows_both_plans_share(): void
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('data-compare-diff', $html);
        $this->assertStringContainsString(e(__('pages.pricing.only_different')), $html);
        $row = static function (string $label) use ($html): string {
            $at = (int) strpos($html, '<th scope="row">'.e($label).'</th>');
            $start = (int) strrpos(substr($html, 0, $at), '<tr');

            return substr($html, $start, $at - $start);
        };
        $this->assertStringContainsString('data-same', $row(__('pages.pricing.rows.preview.0')), 'a row both plans have');
        $this->assertStringNotContainsString('data-same', $row(__('pages.pricing.rows.storage.0')), 'storage differs');
        $this->assertStringNotContainsString('data-same', $row(__('pages.pricing.rows.mp3.0')), 'MP3 is Plus only');
    }
}
