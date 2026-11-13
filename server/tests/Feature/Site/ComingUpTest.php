<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\Festivals;
use App\Support\Pages\JewishSongs;
use App\Support\Pages\SongCollections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * /songs leads with the festival coming up (Hillel, 2026-11-12: "have the main songs page automatically highlight which
 * section is coming up next"): one calendar (config site.festivals) for every date-aware collection; a festival's window
 * opens `ahead` days before it and closes after its last day; the one on now, else the nearest, is the card, a second the
 * smaller line, and only a live collection's festival takes part. js/public/coming-up.js picks the same on the visitor's day.
 */
final class ComingUpTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false, 'site.festivals' => [
            ['key' => 'christmas', 'collection' => 'christmas-carols', 'from' => '2026-12-25', 'days' => 1],
            ['key' => 'hanukkah', 'collection' => 'jewish-songs', 'from' => '2026-12-04', 'evening' => true, 'days' => 8, 'ahead' => 21],
            ['key' => 'new-year', 'collection' => 'scottish-songs', 'from' => '2026-12-31', 'days' => 2],
            ['key' => 'tu-bishvat', 'collection' => 'jewish-songs', 'from' => '2027-01-22', 'evening' => true, 'days' => 1, 'ahead' => 0],
            ['key' => 'burns-night', 'collection' => 'scottish-songs', 'from' => '2027-01-25', 'days' => 1],
            ['key' => 'independence-day', 'collection' => 'american-songs', 'from' => '2027-07-04', 'days' => 1],
        ]]);
    }

    /**
     * @param  list<string>  $live
     * @return list<string>
     */
    private static function open(array $live, string $day): array
    {
        return array_column(Festivals::open($live, Carbon::parse($day)), 'key');
    }

    public function test_the_calendar_reads_each_festivals_days_in_order(): void
    {
        $all = Festivals::all();
        $this->assertSame(['hanukkah', 'christmas', 'new-year', 'tu-bishvat', 'burns-night', 'independence-day'], array_column($all, 'key'));
        // A festival from sundown runs its days after its first evening; a day festival ends on its last day.
        $this->assertSame(['2026-12-12', '2026-12-25', '2027-01-01'], array_map(static fn (array $f): string => $f['until']->format('Y-m-d'), array_slice($all, 0, 3)));
        $this->assertSame([21, 35, 35, 0], array_column(array_slice($all, 0, 4), 'ahead'));
    }

    public function test_a_festivals_window_opens_ahead_of_it_and_closes_after_its_last_day(): void
    {
        $hanukkah = Festivals::all()[0];
        $at = static fn (string $day): array => Festivals::state($hanukkah, Carbon::parse($day));
        $this->assertSame(['days' => 22, 'on' => false, 'open' => false], $at('2026-11-12'));
        $this->assertSame(['days' => 21, 'on' => false, 'open' => true], $at('2026-11-13'));
        $this->assertSame(['days' => 0, 'on' => false, 'open' => true], $at('2026-12-04'), 'its first evening: tonight, not yet on');
        $this->assertSame(['days' => -1, 'on' => true, 'open' => true], $at('2026-12-05'));
        $this->assertSame(['days' => -8, 'on' => true, 'open' => true], $at('2026-12-12'));
        $this->assertFalse($at('2026-12-13')['open']);
        $this->assertTrue(Festivals::state(Festivals::all()[1], Carbon::parse('2026-12-25'))['on'], 'a day festival is on on its day');
        $this->assertFalse(Festivals::state(Festivals::all()[3], Carbon::parse('2027-01-22'))['open'], 'ahead 0 never leads /songs');
    }

    public function test_the_one_on_now_leads_else_the_nearest_and_only_live_collections_take_part(): void
    {
        $live = ['jewish-songs', 'christmas-carols'];
        $this->assertSame(['hanukkah', 'christmas'], self::open($live, '2026-11-20'));
        $this->assertSame(['hanukkah', 'christmas'], self::open($live, '2026-12-10'), 'Hanukkah on: the card; Christmas near: the line');
        $this->assertSame(['christmas'], self::open($live, '2026-12-20'));
        $this->assertSame([], self::open($live, '2026-12-28'), 'New Year and Burns Night wait for a Scottish page');
        $this->assertSame(['new-year', 'burns-night'], self::open([...$live, 'scottish-songs'], '2026-12-28'));
        $this->assertSame([], self::open([...$live, 'american-songs'], '2027-07-15'));
        $this->assertSame(['hanukkah'], self::open(['jewish-songs'], '2026-11-13'));
        $this->assertTrue(JewishSongs::inSeason() === (self::open(['jewish-songs'], now()->format('Y-m-d')) !== []));
    }

    public function test_songs_shows_the_coming_festival_as_its_card_and_moves_its_panel_first(): void
    {
        $this->catalogue();
        if (count(app(SongCollections::class)->members(JewishSongs::SLUG)) < SongCollections::MIN) {
            $this->markTestSkipped('fewer than four Jewish songs open in this content');
        }
        $this->travelTo('2026-11-20 12:00:00');
        $html = (string) $this->get('/songs')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<section class="s-wrap s-sh-next" aria-label="Coming up" data-coming-up\s*>#', $html);
        // Hanukkah's card shown, in its collection's tone, with its Hanukkah songs and the way to the page; the rest hidden.
        // The lamp isn't in the card here: /songs's Jewish panel already draws it (never twice on a page).
        $this->assertMatchesRegularExpression('#<aside class="s-jcount s-tone-jewish-songs s-sh-next-card"[^>]*data-key="hanukkah"[^>]*data-from="2026-12-04"[^>]*data-evening[^>]*>\s*<p id="next-\d+-k" class="s-jcount-k" data-kick>Coming up</p>#', $html);
        $this->assertMatchesRegularExpression('#data-key="hanukkah"(?:(?!</aside>).)*In 14 days(?:(?!</aside>).)*From sundown, Friday 4 December(?:(?!</aside>).)*href="/songs/maoz-tzur"(?:(?!</aside>).)*All \d+ Jewish songs#s', $html);
        $this->assertMatchesRegularExpression('#data-key="christmas"[^>]*hidden#', $html);
        // Christmas, a little further, is the smaller line under it.
        $this->assertMatchesRegularExpression('#<p class="s-sh-next-also" data-also="\d+"\s*>Also coming up: <a href="/songs/christmas-carols" class="s-lk">Christmas</a>, <span data-when>In 35 days</span></p>#', $html);
        // The Jewish songs' panel first in the grid, each panel with its usual place for the script.
        preg_match_all('#<section id="band-([a-z-]+)" class="s-sh-panel[^"]*"[^>]*data-order="(\d+)"#', $html, $panels);
        $this->assertSame('jewish-songs', $panels[1][0]);
        $this->assertStringContainsString('<script type="module" src="/js/public/coming-up.js"></script>', $html);

        // Nothing near: no card, the panels in their usual order.
        $this->travelTo('2027-07-15 12:00:00');
        $quiet = (string) $this->get('/songs')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-coming-up', $quiet);
    }
}
