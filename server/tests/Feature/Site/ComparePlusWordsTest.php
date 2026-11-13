<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The comparisons say Plus's price as /pricing does: while checkout is closed, that Plus isn't open yet, never a price as
 * if it could be bought today; once both Stripe prices are set, the plain price.
 */
final class ComparePlusWordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('compare_pages', 'on', null, 'Testing the comparisons');
    }

    public function test_while_checkout_is_closed_plus_is_said_to_be_not_open_yet(): void
    {
        config(['billing.prices.month' => null, 'billing.prices.year' => null]);
        $html = html_entity_decode((string) $this->get('/compare/howli')->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString("Plus isn't open yet; it will be", $html);
        $this->assertStringContainsString('Plus, not open yet (it will be', $html);
        $this->assertStringNotContainsString('Plus is $', $html);
        $this->assertStringNotContainsString(':plus_', $html);
    }

    public function test_once_checkout_opens_the_price_is_said_plainly(): void
    {
        config(['billing.prices.month' => 'price_month', 'billing.prices.year' => 'price_year']);
        $html = html_entity_decode((string) $this->get('/compare/howli')->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);

        $this->assertMatchesRegularExpression('/Plus is \S+ a month or \S+ a year/', $html);
        $this->assertStringContainsString('Plus, for ', $html);
        $this->assertStringNotContainsString('not open yet', $html);
    }
}
