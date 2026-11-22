<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\PlusFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plus's features, once (Hillel's approved marketing review): marketing's order, keyed on the switches, numbers from
 * config, as a list and as one line.
 */
final class PlusFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_nothing_switched_on_plus_is_storage_stems_and_private_arrangements(): void
    {
        $this->assertSame(['storage', 'stems', 'private'], PlusFeatures::keys());
        $this->assertSame('Plus adds more storage, stems and private arrangements.', PlusFeatures::sentence());
        $this->assertSame(['Room for every take.', '50 GB for your recordings.'], PlusFeatures::items()[0]);
    }

    public function test_the_choir_and_singing_together_join_with_their_switches_in_order(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'test');
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        config(['own_choir.plus_minutes' => 60]);

        $this->assertSame(['choir', 'together', 'storage', 'stems', 'private'], PlusFeatures::keys());
        $this->assertSame('Plus adds about 15 songs a month in a voice like yours, singing together, more storage, stems and private arrangements.', PlusFeatures::sentence());
        $this->assertSame('About 15 songs a month (60 minutes of AI singing), each one yours to download.', PlusFeatures::items()[0][1]);

        app(Flags::class)->set('choir_seats', 'on', null, 'test');
        $this->assertStringContainsString('your seat in any choir group', PlusFeatures::items()[1][1]);
    }

    public function test_in_spanish(): void
    {
        app()->setLocale('es');

        $this->assertSame('Plus añade más almacenamiento, pistas por separado y arreglos privados.', PlusFeatures::sentence());
    }

    public function test_the_plan_cards_read_the_one_list(): void
    {
        $off = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString(e('Room for every take.'), $off);
        $this->assertStringNotContainsString(e('More songs in a voice like yours.'), $off);

        app(Flags::class)->set('own_choir', 'on', null, 'test');
        $on = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString(e('More songs in a voice like yours.'), $on);
        $this->assertSame(1, substr_count($on, e('More songs in a voice like yours.')), 'Once, never twice.');
        $this->assertLessThan(strpos($on, e('Room for every take.')), strpos($on, e('More songs in a voice like yours.')), 'In marketing\'s order.');
    }

    public function test_a_surfaces_own_words_fill_from_the_list(): void
    {
        $this->assertSame(
            ['Plus adds more storage, stems and private arrangements.', 'more storage, stems and private arrangements', '15', '60'],
            array_values(PlusFeatures::replacements()),
        );
    }
}
