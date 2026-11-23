<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A song in your own voice downloads with Plus (Hillel, 2026-11-21: "if they want to download it they need to subscribe
 * to plus"): the pricing page says so in its comparison, only while own_choir is on.
 */
final class ChoirDownloadPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_lists_the_choir_download_as_plus_only_while_own_choir_is_on(): void
    {
        $label = e((string) __('pages.pricing.choir_row.0'));
        $this->assertStringNotContainsString($label, (string) $this->get('/pricing')->assertOk()->getContent());

        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString($label, $html);
        // How many: about 15 a month with Plus, never unlimited (60 minutes of AI singing).
        $this->assertStringContainsString('60 minutes a month', $html);
    }
}
