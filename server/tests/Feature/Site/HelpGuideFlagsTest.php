<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\HelpGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * A help section or line behind a switch shows only while the switch allows it.
 */
final class HelpGuideFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The page's own words loaded first, then these in place of its sections.
        __('pages.help.pages.create.title');
        Lang::addLines(['pages.help.pages.create.sections' => [
            ['Where to start', ['Pick a song.', ['text' => 'Or hum a tune.', 'flag' => 'your_tune'], ['text' => 'Hum a tune is coming.', 'unless' => 'your_tune'], 'Then sing.'], 'steps' => true],
            ['Hum a tune', ['Hum it and we write it down.'], 'flag' => 'your_tune'],
            ['Hum a tune', ['A heading the hidden one would have taken.']],
        ]], 'en');
    }

    public function test_a_switched_section_and_lines_show_only_while_the_switch_is_on(): void
    {
        app(Flags::class)->set('your_tune', 'off', null, 'Not yet');
        $off = HelpGuide::sections('create');
        $this->assertSame(['where-to-start', 'hum-a-tune'], array_column($off, 'id'));
        $this->assertSame(['Pick a song.', 'Hum a tune is coming.', 'Then sing.'], $off[0]['blocks'][0]['items']);
        $this->assertSame(['A heading the hidden one would have taken.'], $off[1]['blocks'][0]['items']);
        $this->assertStringNotContainsString('Hum it and we write it down.', implode(' ', array_column(HelpGuide::searchIndex(), 'text')));

        app(Flags::class)->set('your_tune', 'on', null, 'Open');
        $on = HelpGuide::sections('create');
        $this->assertSame(['where-to-start', 'hum-a-tune', 'hum-a-tune-2'], array_column($on, 'id'));
        $this->assertSame(['Pick a song.', 'Or hum a tune.', 'Then sing.'], $on[0]['blocks'][0]['items']);
        $this->assertSame(1, $on[0]['blocks'][0]['start']);
        $this->assertStringContainsString('Hum it and we write it down.', implode(' ', array_column(HelpGuide::searchIndex(), 'text')));
    }

    public function test_where_contact_us_is_follows_feedback2_in_english_and_spanish(): void
    {
        // feedback2 moves Contact us to the top of Settings (docs/feedback-plan.md): the help says where it is either way.
        app(Flags::class)->set('lang_es', 'on', null, 'test');
        $read = fn (string $path): string => html_entity_decode(strip_tags((string) $this->get($path)->assertOk()->getContent()), ENT_QUOTES | ENT_HTML5);
        $choir = static fn (): string => (string) json_encode(HelpGuide::sections('your-choir'), JSON_UNESCAPED_UNICODE);

        app(Flags::class)->set('feedback2', 'off', null, 'test');
        $this->assertStringContainsString('Contact us is at the bottom of Settings.', $read('/help/settings'));
        $this->assertStringContainsString('Contacto está al final de Ajustes.', $read('/es/help/settings'));
        $this->assertStringNotContainsString('Contact us is at the top of Settings', $read('/help/settings'));
        $this->assertStringContainsString('at the bottom of **Settings**', $choir());

        app(Flags::class)->set('feedback2', 'on', null, 'test');
        $this->assertStringContainsString('Contact us is at the top of Settings: on Home, tap Settings.', $read('/help/settings'));
        $this->assertStringContainsString('Contacto está al principio de Ajustes: en la pantalla de inicio, toca Ajustes.', $read('/es/help/settings'));
        $this->assertStringNotContainsString('Contact us is at the bottom of Settings', $read('/help/settings'));
        $this->assertStringContainsString('Tap **Contact us** in **Settings**.', $choir());
        $this->assertStringNotContainsString('at the bottom of **Settings**', $choir());
    }
}
