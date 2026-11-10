<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A song's part page as a visitor and a search engine see it (site/song-part.blade.php): the part named in the title
 * and heading, its range and key, the player for a free song with its words from lang, the other parts linked, and the
 * song and its trail in structured data.
 */
final class SongPartViewTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_every_open_songs_part_page_plays_for_a_guest(): void
    {
        // Silent Night isn't one the app bundles, but it's open, so its notes reach a guest and its page plays
        // (Hillel, 2026-11-08). Before, its player met a guest's 403 and collapsed, shifting the page (0.12).
        $this->assertNotContains('silent-night', (array) config('templates.free'));
        $this->get('/songs/silent-night/alto')->assertOk()
            ->assertSee('data-part-player', false)->assertSee('song-part-page.js', false)
            ->assertDontSee(__('site.song_part.plus_note'));
        $this->get('/api/templates/silent-night')->assertOk();
    }

    public function test_a_harmony_part_page_names_the_part_and_plays_it(): void
    {
        $html = (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Amazing Grace Alto Part: Hear It, Learn It | Stackapella</title>', $html);
        $this->assertStringContainsString('<h1 id="page-h">Amazing Grace: alto part</h1>', $html);
        $this->assertStringContainsString('B3 to B4', $html);
        $this->assertStringContainsString('<div class="s-spart-player" data-part-player></div>', $html);
        $this->assertSame(1, preg_match('/<script type="application\/json" data-part-player-data>(.*?)<\/script>/s', $html, $m));
        $player = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($player);
        $this->assertSame(['amazing-grace', 'alto'], [$player['slug'], $player['part']]);
        $this->assertSame(':hit of :total notes lit', $player['words']['score']);
        foreach (['soprano', 'tenor', 'bass'] as $other) {
            $this->assertStringContainsString('/songs/amazing-grace/'.$other.'"', $html, $other);
        }
        $this->assertStringContainsString('"@type":"MusicComposition"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="', $html);
    }

    public function test_the_melody_part_says_so(): void
    {
        $html = (string) $this->get('/songs/amazing-grace/soprano')->assertOk()->getContent();

        $this->assertStringContainsString('<h1 id="page-h">Amazing Grace: the melody (soprano)</h1>', $html);
    }
}
