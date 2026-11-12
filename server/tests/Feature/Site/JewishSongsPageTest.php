<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Pages\JewishSongs;
use App\Support\Pages\SongCollections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The Jewish songs page (Hillel, 2026-11-11: "a 'traditional jewish songs' page like we have a carols page"): a song
 * collection with a countdown to the next festival, each song's festival as a tag, the coming festival's songs first,
 * an intro that says whose four parts each song keeps, and a season on /songs around Hanukkah and Passover.
 */
final class JewishSongsPageTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false, 'site.jewish_festivals' => [
            ['key' => 'hanukkah', 'from' => '2026-12-04', 'until' => '2026-12-12'],
            ['key' => 'passover', 'from' => '2027-04-21', 'until' => '2027-04-29'],
        ]]);
    }

    public function test_the_countdown_names_the_next_festival_and_the_one_on_now(): void
    {
        $this->travelTo('2026-11-11 12:00:00');
        $this->assertSame(['hanukkah', 23, false], [JewishSongs::festival()['key'] ?? null, JewishSongs::festival()['days'] ?? null, JewishSongs::festival()['on'] ?? null]);

        $this->travelTo('2026-12-04 09:00:00');
        $this->assertSame([0, true], [JewishSongs::festival()['days'] ?? null, JewishSongs::festival()['on'] ?? null]);

        $this->travelTo('2026-12-13 09:00:00');
        $this->assertSame('passover', JewishSongs::festival()['key'] ?? null);

        $this->travelTo('2027-05-01 09:00:00');
        $this->assertNull(JewishSongs::festival(), 'none ahead in the config: no countdown');
    }

    public function test_its_season_on_songs_is_three_weeks_before_hanukkah_and_passover_until_they_end(): void
    {
        $this->travelTo('2026-11-12 12:00:00');
        $this->assertFalse(JewishSongs::inSeason());
        $this->travelTo('2026-11-13 12:00:00');
        $this->assertTrue(JewishSongs::inSeason());
        $this->travelTo('2026-12-12 12:00:00');
        $this->assertTrue(JewishSongs::inSeason());
        $this->travelTo('2027-01-15 12:00:00');
        $this->assertFalse(JewishSongs::inSeason());
        $this->travelTo('2027-04-01 12:00:00');
        $this->assertTrue(JewishSongs::inSeason());
    }

    public function test_a_songs_tag_is_its_first_festival_else_every_day(): void
    {
        $this->assertSame('hanukkah', JewishSongs::tag(['jewish', 'hanukkah', 'traditional']));
        $this->assertSame('shabbat', JewishSongs::tag(['Jewish', 'Shabbat']));
        $this->assertSame('every-day', JewishSongs::tag(['jewish', 'traditional']));
    }

    public function test_the_page_shows_its_countdown_tags_and_hanukkah_songs_first_before_hanukkah(): void
    {
        $this->catalogue();
        if (count(app(SongCollections::class)->members(JewishSongs::SLUG)) < SongCollections::MIN) {
            $this->markTestSkipped('fewer than four Jewish songs open in this content');
        }
        $this->travelTo('2026-11-11 12:00:00');
        $html = (string) $this->get('/songs/jewish-songs')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Jewish Songs for Hanukkah, Passover and Shabbat</title>', $html);
        $this->assertStringContainsString('class="s-xhero s-tone s-tone-jewish-songs"', $html);
        $this->assertStringContainsString('Hanukkah', $html);
        $this->assertStringContainsString(trans_choice('site.collections.jewish-songs.count_in', 23, ['count' => 23]), $html);
        preg_match_all('#class="s-xplace">([^<]+)<#', $html, $tags);
        $this->assertNotEmpty($tags[1]);
        $this->assertSame('Hanukkah', $tags[1][0], 'the coming festival\'s songs first');
    }

    public function test_the_intro_names_every_song_that_keeps_someone_elses_parts(): void
    {
        $this->catalogue();
        $intro = implode(' ', (array) __('site.collections.jewish-songs.intro'));
        // Ein Keloheinu keeps the Union Hymnal's four parts, as the intro says; every other song's parts are ours (on a
        // printed harmony or not): another arranger's setting joining must change the words first.
        foreach (app(SongCollections::class)->members(JewishSongs::SLUG) as $slug) {
            $template = Template::query()->with('currentVersion')->where('slug', $slug)->firstOrFail();
            $credit = (string) (((array) (((array) $template->currentVersion?->data)['credits'] ?? []))['arrangement'] ?? '');
            if ($slug === 'ein-keloheinu') {
                $this->assertStringContainsString('Union Hymnal', $credit);
                $this->assertStringContainsString('Union Hymnal', $intro);

                continue;
            }
            $this->assertStringStartsWith('Stackapella', $credit, $slug);
        }
        // God's names: the substituted forms where a song names God, as its page says (Hillel; the courses lead's wording).
        $this->assertStringContainsString('substituted forms', $intro);
        $this->assertStringContainsString('adapted from the Union Hymnal', $intro);
    }
}
