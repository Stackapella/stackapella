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
        $this->assertSame('Plus adds more storage, each voice as its own file and private arrangements.', PlusFeatures::sentence());
        $this->assertSame(['Room for every take.', '50 GB for your recordings.'], PlusFeatures::items()[0]);
    }

    public function test_the_choir_and_singing_together_join_with_their_switches_in_order(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'test');
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        config(['own_choir.plus_minutes' => 60]);

        $this->assertSame(['choir', 'together', 'storage', 'stems', 'private'], PlusFeatures::keys());
        $this->assertSame('Plus adds about 15 songs a month in a voice like yours, singing together, more storage, each voice as its own file and private arrangements.', PlusFeatures::sentence());
        $this->assertSame('Every part of a song sung by AI in a voice like yours: 60 minutes a month, about 15 songs, each one yours to download.', PlusFeatures::items()[0][1]);

        // With choir seats on, a group starts free: Plus leads with a singer's own work and singing together comes last.
        app(Flags::class)->set('choir_seats', 'on', null, 'test');
        $this->assertSame(['choir', 'storage', 'stems', 'private', 'together'], PlusFeatures::keys());
        $last = PlusFeatures::items()[4];
        $this->assertSame('Sing with friends.', $last[0]);
        $this->assertStringContainsString('your seat in any choir group', $last[1]);
        $this->assertStringNotContainsString(e('Start choir groups.'), (string) $this->get('/pricing')->assertOk()->getContent());
    }

    public function test_in_spanish(): void
    {
        app()->setLocale('es');

        $this->assertSame('Plus añade más almacenamiento, cada voz en su propio archivo y arreglos privados.', PlusFeatures::sentence());
    }

    public function test_the_plan_cards_read_the_one_list(): void
    {
        $off = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString(e('Room for every take.'), $off);
        $this->assertStringNotContainsString(e('Your own AI choir.'), $off);

        app(Flags::class)->set('own_choir', 'on', null, 'test');
        $on = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString(e('Your own AI choir.'), $on);
        $this->assertSame(1, substr_count($on, e('Your own AI choir.')), 'Once, never twice.');
        $this->assertLessThan(strpos($on, e('Room for every take.')), strpos($on, e('Your own AI choir.')), 'In marketing\'s order.');
    }

    public function test_a_surfaces_own_words_fill_from_the_list(): void
    {
        $this->assertSame(
            ['Plus adds more storage, each voice as its own file and private arrangements.', 'more storage, each voice as its own file and private arrangements', '15', '60'],
            array_values(PlusFeatures::replacements()),
        );
    }

    public function test_the_app_reads_the_same_plus_numbers_as_the_sites_words(): void
    {
        config(['own_choir.plus_minutes' => 60, 'usage.plans.plus.storage_bytes' => 50 * 1024 ** 3]);
        $this->assertSame(['choirMinutes' => 60, 'choirSongs' => 15, 'storageBytes' => 53687091200], PlusFeatures::limits());

        $shown = $this->getJson('/api/auth/config');
        $this->assertMatchesContract($shown, 'auth.config', 200);
        $shown->assertJsonPath('data.plusLimits', ['choirMinutes' => 60, 'choirSongs' => 15, 'storageBytes' => 53687091200]);

        // A month too short for a song still says one.
        config(['own_choir.plus_minutes' => 3]);
        $this->assertSame(1, PlusFeatures::limits()['choirSongs']);
    }
}
