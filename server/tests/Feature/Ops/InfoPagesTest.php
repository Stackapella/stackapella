<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Support\Http\Hosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing, pricing and help pages (docs/decisions.md, "who builds the public pages").
 */
final class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_time_visitor_gets_the_landing_and_anyone_who_used_the_app_gets_the_app(): void
    {
        $landing = $this->get('/')->assertOk()->assertViewIs('pages.landing');
        $landing->assertSee(__('pages.landing.headline'))->assertSee('href="/learn/1-comfortable"', false);
        $landing->assertHeader('Cache-Control', 'no-cache, private');
        $this->assertStringContainsString('Cookie', (string) $landing->headers->get('Vary'));
        $this->assertSame([], $landing->headers->getCookies());

        $this->assertShell($this->get('/?app')->getContent());
        $this->assertShell($this->withUnencryptedCookie('sa_app', '1')->get('/')->getContent());
        $this->assertShell($this->withCookie((string) config('session.cookie'), 'x')->get('/')->getContent());
    }

    public function test_pricing_shows_the_configs_prices_in_the_visitors_currency(): void
    {
        $page = $this->get('/pricing')->assertOk()->assertViewIs('pages.pricing');
        $page->assertSee('$3.33')->assertSee('$39.99')->assertSee('$4.99')->assertSee(__('pages.pricing.tax_added'));
        $page->assertSee('1 GB')->assertSee('50 GB');

        $this->get('/pricing', ['CF-IPCountry' => 'GB'])->assertSee('£34.99')->assertSee('£4.49')->assertSee(__('pages.pricing.tax_included'));
        $this->get('/pricing', ['CF-IPCountry' => 'DE'])->assertSee('€39.99');
        $this->get('/pricing', ['Accept-Language' => 'en-GB,en;q=0.8'])->assertSee('£34.99');
        $this->get('/pricing?currency=eur', ['CF-IPCountry' => 'GB'])->assertSee('€39.99');
        $this->get('/pricing?currency=yen')->assertSee('$39.99');

        // Without Stripe's prices, Checkout is paused and the page says so.
        config(['billing.prices.month' => null]);
        $this->get('/pricing')->assertSee(__('pages.landing.plans_paused'))->assertDontSee('href="/plus?from=pricing"', false);
        config(['billing.prices' => ['month' => 'price_m', 'year' => 'price_y']]);
        $this->get('/pricing')->assertSee('href="/plus?from=pricing"', false);
    }

    public function test_the_help_pages_answer_the_common_problems(): void
    {
        $index = $this->get('/help')->assertOk()->assertViewIs('pages.help');
        foreach (['mic', 'headphones', 'timing', 'iphone', 'guides'] as $slug) {
            $index->assertSee('href="/help/'.$slug.'"', false);
            $this->get('/help/'.$slug)->assertOk()->assertViewIs('pages.help-page')->assertSee(__('pages.help.pages.'.$slug.'.title'));
        }

        $this->get('/help/nope')->assertNotFound();
        $this->get('/help/iphone')->assertSee('Add to Home Screen')->assertSee('href="'.e(Hosts::app('/contact?from=%2Fhelp%2Fiphone')).'"', false)->assertSee('contact@stackapella.com');
        $index->assertSee('href="'.e(Hosts::app('/contact?from=%2Fhelp')).'"', false);
        $this->assertSame('noindex', $this->get('/help')->headers->get('X-Robots-Tag'));
    }
}
