<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\Carols;
use App\Support\Pages\JewishSongs;
use App\Support\Pages\SongsHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * /songs through the holidays, on a fixed clock (the coordinator, 2026-11-12: "holiday-season growth"): the carols'
 * band from November to Twelfth Night, the Jewish songs first from 21 days before Hanukkah until it ends, and the
 * season's chip and tonight's carol as each day says.
 */
final class SongsSeasonTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    /** @return list<string> the bands' keys, in order */
    private function bands(string $at): array
    {
        $this->travelTo($at);
        $this->app->forgetScopedInstances();

        return array_column(app(SongsHub::class)->bands(), 'key');
    }

    public function test_the_bands_follow_the_season(): void
    {
        // The day before Hanukkah's 21 days: the carols lead, then the panels by size.
        $this->assertSame('christmas', $this->bands('2026-11-12 12:00:00')[0]);
        $this->assertNotSame(JewishSongs::SLUG, $this->bands('2026-11-12 12:00:00')[1]);

        // Up to Thanksgiving (26 November), its hymns come right after the carols, the festival nearest first (Festivals).
        $this->assertSame(['christmas', 'thanksgiving-hymns'], array_slice($this->bands('2026-11-13 08:00:00'), 0, 2));

        // Then the Jewish songs, from Hanukkah's 21 days ahead of its first evening (4 December) to its last day, 12 December.
        foreach (['2026-11-27 08:00:00', '2026-12-04 20:00:00', '2026-12-12 20:00:00'] as $at) {
            $this->assertSame(['christmas', JewishSongs::SLUG], array_slice($this->bands($at), 0, 2), $at);
        }
        $this->assertNotSame(JewishSongs::SLUG, $this->bands('2026-12-13 08:00:00')[1]);

        // The carols' band runs to Twelfth Night, the landing's link only to 26 December.
        $this->assertSame('christmas', $this->bands('2027-01-06 20:00:00')[0]);
        $this->assertFalse(Carols::promoted());
        $this->assertNotContains('christmas', $this->bands('2027-01-07 08:00:00'));
        $this->assertNotContains('christmas', $this->bands('2027-10-31 12:00:00'));
        $this->assertSame('christmas', $this->bands('2027-11-01 08:00:00')[0]);
    }

    public function test_the_sample_cards_stay_light(): void
    {
        // site_integration's OWN_KB (2026-11-14): a card plays the notes and shows no lyric, so its line carries no words, and
        // Play and Stop are drawn once in the page's sprite rather than on every card.
        $this->travelTo('2026-12-01 09:00:00');
        $html = (string) $this->get('/songs')->assertOk()->getContent();
        preg_match_all('#<li class="s-sh-song"[^>]*>.*?</li>#s', $html, $cards);
        $this->assertGreaterThan(10, count($cards[0]));
        foreach ($cards[0] as $card) {
            $this->assertStringNotContainsString('"words"', $card);
            $this->assertStringNotContainsString('<path', $card);
        }
        $this->assertSame(1, substr_count($html, 'id="si-play"'));
        $this->assertSame(count($cards[0]), substr_count($html, 'href="#si-play"'));
    }

    public function test_the_page_says_when_the_season_ends_and_tonights_carol_is_one_a_day(): void
    {
        $this->travelTo('2026-12-01 09:00:00');
        $this->get('/songs')->assertOk()->assertSee('6 January');

        $carols = app(Carols::class)->songs();
        $this->assertNotEmpty($carols);
        $morning = Carols::tonight($carols);
        $this->travelTo('2026-12-01 23:30:00');
        $this->assertSame($morning, Carols::tonight($carols), 'the same carol all day, for everyone');
        $this->travelTo('2026-12-02 00:30:00');
        $this->assertNotSame($morning['slug'] ?? null, Carols::tonight($carols)['slug'] ?? null, 'a new one the next day');
    }
}
