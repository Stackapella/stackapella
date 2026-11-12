<?php

declare(strict_types=1);

namespace Tests\Unit\Rights;

use App\Support\Rights\SongRights;
use Tests\TestCase;

/**
 * A song's rights by country (docs/geo-rights-plan.md), pinned on the cases that made the plan: Lift Every Voice
 * (J. Rosamond Johnson died 1954), We Gather Together's English (Theodore Baker died 1934), Silent Night.
 */
final class SongRightsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['copyright_terms.countries' => [
            'US' => ['life' => 70, 'publicationBased' => ['freeBefore' => 1931, 'term' => 95]],
            'GB' => ['life' => 70, 'shorterTerm' => false],
            'DE' => ['life' => 70, 'shorterTerm' => true],
            'MX' => ['life' => 100],
            'CO' => ['life' => 80],
            'ES' => ['life' => 70, 'exceptions' => [['diedBefore' => '1987-12-07', 'life' => 80]]],
            'CA' => ['life' => 70, 'freeIfDiedBefore' => 1972],
        ]]);
    }

    /** @return array<string, mixed> */
    private static function liftEveryVoice(): array
    {
        return ['makers' => [['name' => 'J. Rosamond Johnson', 'role' => 'music', 'died' => 1954], ['name' => 'James Weldon Johnson', 'role' => 'words', 'died' => 1938]], 'firstPublished' => ['year' => 1900, 'country' => 'US']];
    }

    public function test_lift_every_voice_is_free_in_the_us_and_protected_where_terms_run_longer(): void
    {
        $song = self::liftEveryVoice();
        $at = static fn (string $country): array => SongRights::status($song, $country, 2026);

        $this->assertTrue($at('US')['free'], 'printed in 1900');
        $this->assertTrue($at('GB')['free'], 'life+70: free from 2025');
        $this->assertSame(['free' => false, 'until' => 2055, 'reason' => 'term'], $at('MX'));
        $this->assertSame(2035, $at('CO')['until']);
        $this->assertSame(2035, $at('ES')['until'], 'died before 1987: life+80');
        $this->assertTrue($at('CA')['free'], 'died before 1972: the extension revived nothing');
        // Somewhere we can't place: the strictest of every rule.
        $this->assertSame(2055, SongRights::status($song, 'XX', 2026)['until']);
        $this->assertSame(2055, SongRights::status($song, null, 2026)['until']);
    }

    public function test_we_gather_togethers_english_and_silent_night(): void
    {
        $baker = ['makers' => [['name' => 'Theodore Baker', 'role' => 'translation', 'died' => 1934]], 'firstPublished' => ['year' => 1894, 'country' => 'US']];
        $this->assertSame(2035, SongRights::status($baker, 'MX', 2026)['until']);
        $this->assertTrue(SongRights::status($baker, 'MX', 2035)['free'], 'free from 1 January 2035');
        $this->assertTrue(SongRights::status($baker, 'ES', 2026)['free'], 'life+80: free from 2015');

        $silent = ['makers' => [['name' => 'Franz Xaver Gruber', 'role' => 'music', 'died' => 1863], ['name' => 'Joseph Mohr', 'role' => 'words', 'died' => 1848]], 'firstPublished' => ['year' => 1833, 'country' => 'DE']];
        foreach (['US', 'GB', 'DE', 'MX', 'CO', 'ES', 'CA', 'XX'] as $country) {
            $this->assertTrue(SongRights::status($silent, $country, 2026)['free'], $country);
        }
    }

    public function test_unknown_data_withholds_and_the_shorter_term_only_where_the_law_applies_it(): void
    {
        // A maker whose death is unknown, or living, or no first printing: protected, until unknown.
        $this->assertSame(['free' => false, 'until' => null, 'reason' => 'unknown'], SongRights::status(['makers' => [['name' => 'A', 'died' => 'unknown']], 'firstPublished' => ['year' => 1900, 'country' => 'GB']], 'GB', 2026));
        $this->assertFalse(SongRights::status(['makers' => [['name' => 'B', 'died' => 'living']], 'firstPublished' => ['year' => 1900, 'country' => 'GB']], 'GB', 2026)['free']);
        $this->assertFalse(SongRights::status(['makers' => [['name' => 'C', 'died' => 1900]]], 'US', 2026)['free'], 'the US needs the printing date');

        // A US song (1925) whose composer died in 1960: free in the US by publication. Germany applies the shorter
        // term, so it's free there too; Britain, as this table has it, doesn't, so life+70 runs to 2030.
        $song = ['makers' => [['name' => 'D', 'died' => 1960]], 'firstPublished' => ['year' => 1925, 'country' => 'US']];
        $this->assertTrue(SongRights::status($song, 'US', 2026)['free']);
        $this->assertTrue(SongRights::status($song, 'DE', 2026)['free']);
        $this->assertSame(2031, SongRights::status($song, 'GB', 2026)['until']);

        // A real country missing from the table is never a silent guess: the strictest rule, marked.
        $this->assertSame('no-rule', SongRights::status($song, 'FR', 2026)['reason']);

        // Traditional words and tune (no makers), printed in 1880: free everywhere.
        $this->assertTrue(SongRights::status(['makers' => [], 'firstPublished' => ['year' => 1880, 'country' => 'GB']], 'MX', 2026)['free']);
    }

    public function test_the_shaker_songs_us_notice_renewal_and_a_song_never_printed(): void
    {
        // Simple Gifts: written 1848, first printed 1937 (Andrews), its renewal unknown: the US's 95 years, to be safe.
        $gifts = ['makers' => [], 'made' => 1848, 'firstPublished' => ['year' => 1937, 'country' => 'US', 'notice' => 'unknown', 'renewed' => 'unknown']];
        $this->assertSame(2033, SongRights::status($gifts, 'US', 2026)['until']);
        // Not renewed (it would have been due in 1965): its US copyright ended after 28 years. No notice: never protected.
        $this->assertTrue(SongRights::status(['firstPublished' => ['renewed' => false] + $gifts['firstPublished']] + $gifts, 'US', 2026)['free']);
        $this->assertTrue(SongRights::status(['firstPublished' => ['notice' => false] + $gifts['firstPublished']] + $gifts, 'US', 2026)['free']);
        // Anonymous elsewhere: from its first printing by the country's life term (Mexico's 100), to be safe.
        $this->assertTrue(SongRights::status($gifts, 'GB', 2026)['free']);
        $this->assertSame(2038, SongRights::status($gifts, 'MX', 2026)['until']);

        // I Will Bow and the others: printed 1940 with a notice and renewed in 1967: 95 years in the US.
        $bow = ['makers' => [], 'made' => 1847, 'firstPublished' => ['year' => 1940, 'country' => 'US', 'notice' => true, 'renewed' => true]];
        $this->assertSame(2036, SongRights::status($bow, 'US', 2026)['until']);

        // No relief for a foreign printing (its US copyright may have been restored), nor for one printed from 1964
        // (renewal was automatic).
        $this->assertSame(2033, SongRights::status(['firstPublished' => ['year' => 1937, 'country' => 'GB', 'renewed' => false]] + $gifts, 'US', 2026)['until']);
        $this->assertSame(2060, SongRights::status(['firstPublished' => ['year' => 1964, 'country' => 'US', 'renewed' => false]] + $gifts, 'US', 2026)['until']);

        // Heavenly Treasures: no printing found, written about 1850: the US's 120 years from its making, free everywhere.
        $treasures = ['makers' => [], 'made' => 1850];
        foreach (['US', 'GB', 'MX', 'XX'] as $country) {
            $this->assertTrue(SongRights::status($treasures, $country, 2026)['free'], $country);
        }
        // Without either year, it can't be known.
        $this->assertSame('unknown', SongRights::status(['makers' => []], 'US', 2026)['reason']);
    }

    public function test_the_words_have_their_own_status(): void
    {
        // Lift Every Voice in Mexico: the words (James Weldon Johnson, died 1938) are free from 2039, the song from 2055.
        $song = self::liftEveryVoice();
        $this->assertSame(2055, SongRights::status($song, 'MX', 2026)['until']);
        $this->assertSame(2039, SongRights::status($song, 'MX', 2026, SongRights::WORDS)['until']);
        $this->assertSame(2039, SongRights::status($song, 'XX', 2026, SongRights::WORDS)['until']);

        // A tune by a known composer on traditional words printed in 1900: the words go by the printing, free.
        $tune = ['makers' => [['name' => 'E', 'role' => 'music', 'died' => 1954]], 'firstPublished' => ['year' => 1900, 'country' => 'US']];
        $this->assertFalse(SongRights::status($tune, 'MX', 2026)['free']);
        $this->assertTrue(SongRights::status($tune, 'MX', 2026, SongRights::WORDS)['free']);
    }
}
