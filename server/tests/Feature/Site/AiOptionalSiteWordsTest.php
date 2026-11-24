<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI as a choice (docs/ai-optional-plan.md): while ai_optional is off, help and pricing say nothing of the AI features
 * switch; once on, Settings' help has its section, the choir and privacy pages a line each, and pricing a question.
 */
final class AiOptionalSiteWordsTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/help/settings', '/help/privacy', '/pricing'];

    public function test_off_help_and_pricing_say_nothing_of_the_switch(): void
    {
        foreach (self::PAGES as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString('AI features', $html, $page);
            $this->assertStringNotContainsString(e(__('pages.pricing.faq_ai.0')), $html, $page);
        }
    }

    public function test_on_help_and_pricing_say_where_the_switch_is_and_what_off_means(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'Live');
        app(Flags::class)->set('ai_optional', 'on', null, 'Live');

        $settings = (string) $this->get('/help/settings')->assertOk()->getContent();
        $this->assertStringContainsString('>AI features<', $settings);
        $this->assertStringContainsString('your voice isn&#039;t lent to anyone', $settings);
        $this->assertStringContainsString('Turning it off deletes nothing', $settings);

        $this->assertStringContainsString('<strong>Turn on AI features</strong>', (string) $this->get('/help/your-choir')->assertOk()->getContent());
        $this->assertStringContainsString('<strong>AI features</strong>, under', (string) $this->get('/help/privacy')->assertOk()->getContent());

        $pricing = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('pages.pricing.faq_ai.0')), $pricing);
        $this->assertStringContainsString('Only Plus&#039;s AI choir songs need them on.', $pricing);
    }

    public function test_the_spanish_pages_say_it_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        app(Flags::class)->set('ai_optional', 'on', null, 'Live');

        $this->assertStringContainsString('¿Tengo que usar la IA?', (string) $this->get('/es/pricing')->assertOk()->getContent());
        $this->assertStringContainsString('tu voz no se presta a nadie', (string) $this->get('/es/help/settings')->assertOk()->getContent());
    }
}
