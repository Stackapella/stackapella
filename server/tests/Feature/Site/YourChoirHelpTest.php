<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Help's Hear yourself as a choir page (the coordinator, 2026-11-23): only while own_choir is on, every number from
 * config, its friends section only with own_choir_together, and linked from /your-choir.
 */
final class YourChoirHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_there_only_while_the_choir_is_on_and_says_its_numbers_from_config(): void
    {
        $this->get('/help/your-choir')->assertNotFound();
        $this->assertStringNotContainsString('/help/your-choir', (string) $this->get('/help')->getContent());

        app(Flags::class)->set('own_choir', 'on', null, 'Live');
        config(['own_choir.plus_minutes' => 90, 'own_choir.daily' => 4, 'own_choir.voice_months' => 6]);
        $html = (string) $this->get('/help/your-choir')->assertOk()->getContent();
        $this->assertStringContainsString('90 minutes of AI singing', $html);
        $this->assertStringContainsString('up to 4 songs a day', $html);
        $this->assertStringContainsString('Unused for 6 months', $html);
        $this->assertStringNotContainsString(':plus_', $html);
        $this->assertStringNotContainsString(e(__('pages.help.pages.your-choir.sections.6.0')), $html, 'friends wait for their switch');
        $this->assertStringContainsString('href="/help/your-choir"', (string) $this->get('/your-choir')->getContent());

        config(['flags.own_choir_together' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('own_choir_together', 'on', null, 'Live');
        $this->assertStringContainsString(e(__('pages.help.pages.your-choir.sections.6.0')), (string) $this->get('/help/your-choir')->getContent());
    }
}
