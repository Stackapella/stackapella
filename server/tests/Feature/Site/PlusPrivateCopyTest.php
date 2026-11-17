<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /pricing says that shared private arrangements need everyone's own Plus (Hillel, 2026-11-17), in English and Spanish,
 * only while the plus_private switch is on: a row in With others and a question.
 */
final class PlusPrivateCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_pricing_says_it_only_once_the_switch_is_on(): void
    {
        $off = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringNotContainsString('Sharing a private arrangement with other singers', $off);
        $this->assertStringNotContainsString('Can I share a private arrangement?', $off);

        app(Flags::class)->set('plus_private', 'on', null, 'test');
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('Sharing a private arrangement with other singers', $html);
        $this->assertStringContainsString('Yes, with Plus for each singer', $html);
        $this->assertStringContainsString('Can I share a private arrangement?', $html);
        $this->assertStringContainsString('everyone in it needs their own Plus, you too', $html);
    }

    public function test_the_spanish_page_says_it_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'test');
        app(Flags::class)->set('plus_private', 'on', null, 'test');
        $html = (string) $this->get('/es/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('Compartir un arreglo privado con otros cantantes', $html);
        $this->assertStringContainsString('Sí, con Plus para cada cantante', $html);
        $this->assertStringContainsString('¿Puedo compartir un arreglo privado?', $html);
        $this->assertStringContainsString('cada persona necesita su propio Plus, también tú', $html);
        $this->assertStringNotContainsString('Can I share a private arrangement?', $html);
    }
}
