<?php

declare(strict_types=1);

namespace Tests\Feature\Languages;

use App\Support\Flags;
use App\Support\Languages\TranslationMaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stack demo in Spanish (frontend2's audit, 2026-11-13: /es/for/a-cappella and /es/for/friends-and-family answered
 * 500): its `order` holds part keys the page's words are keyed by, so they're names, never words, item by item.
 */
final class StackDemoSpanishTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lists_items_are_names_under_an_identifier_key(): void
    {
        $this->assertTrue(TranslationMaps::identifier('site.for.pages.a-cappella.demo.order.1', 'bass'));
        $this->assertTrue(TranslationMaps::identifier('site.for.pages.a-cappella.demo.order', 'bass'));
        $this->assertFalse(TranslationMaps::identifier('site.for.pages.a-cappella.demo.order.1', 'Add the bass'), 'words stay words');
        $this->assertFalse(TranslationMaps::identifier('site.for.pages.a-cappella.demo.steps.1', 'bass'), 'only under an identifier key');
    }

    public function test_the_stack_demo_pages_open_in_spanish(): void
    {
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('lang_es', 'on', null, 'test');
        foreach (['a-cappella', 'friends-and-family'] as $page) {
            $this->get('/es/for/'.$page)->assertOk();
        }
    }
}
