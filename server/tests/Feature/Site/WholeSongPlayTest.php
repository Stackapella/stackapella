<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Pages\Landing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Every song's play button plays the whole song (Hillel, 2026-11-12: "let's always play the full songs. none are that
 * long"): a card or a song's player carries only its opening line's notes, says it's `whole` and where the song ends,
 * and js/public/mixer.js fetches the rest from the catalogue the part pages read; the landing's hero keeps its one line.
 */
final class WholeSongPlayTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_a_songs_line_says_it_plays_whole_and_where_the_song_ends_and_the_heros_keeps_its_line(): void
    {
        $template = Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail();
        $line = Landing::line($template);
        $this->assertNotNull($line);
        $this->assertTrue($line['whole']);
        $this->assertGreaterThan($line['beats'], $line['songBeats']);
        // Only the opening line's notes are in the page; the rest comes on a press.
        foreach ($line['parts'] as $part) {
            foreach ($part['notes'] as $note) {
                $this->assertLessThan($line['beats'], $note['beat']);
            }
        }
        // Its length is the whole song's.
        $seconds = (int) round($line['songBeats'] * 60 / $line['tempo']);
        $this->assertSame(sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60), $line['length']);

        $hero = Landing::hero();
        $this->assertNotNull($hero);
        $this->assertFalse($hero['whole']);
    }

    public function test_the_words_strip_breaks_where_a_sung_line_starts_instead_of_running_lines_together(): void
    {
        $template = Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail();
        $line = Landing::line($template);
        $this->assertNotNull($line);
        $starts = array_values(array_filter(array_slice($line['words'], 1), static fn (array $w): bool => $w['br']));
        $this->assertNotEmpty($starts, 'the opening line holds more than one sung line');

        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<p class="s-lyrics">(.*?)</p>#s', $html, $strip));
        // One block per sung line, each starting with the word the template breaks before.
        $this->assertSame(count($starts) + 1, substr_count($strip[1], '<span data-line>'));
        foreach ($starts as $word) {
            $this->assertStringContainsString('<span data-line><span data-beat="'.$word['beat'].'">', $strip[1], $word['text']);
        }
    }

    public function test_a_collections_cards_and_its_try_player_play_whole_songs_and_the_player_is_named_without_first_line(): void
    {
        $html = (string) $this->get('/songs/hymns')->assertOk()->getContent();
        preg_match_all('#<script type="application/json" data-song>(.*?)</script>#s', $html, $songs);
        $this->assertNotEmpty($songs[1]);
        foreach ($songs[1] as $json) {
            $song = json_decode(html_entity_decode($json), true);
            $this->assertIsArray($song);
            $this->assertSame($song['songBeats'] > $song['beats'], $song['whole'], (string) $song['song']);
        }
        // The try card names its song, not its first line.
        $this->assertStringContainsString('id="try-mixer"', $html);
        $this->assertDoesNotMatchRegularExpression('#id="try-mixer-title">[^<]*'.preg_quote(e(__('pages.landing.hero.title', ['song' => ''])), '#').'#', $html);
        // The play controls' words, once for the page.
        $this->assertSame(1, substr_count($html, 'id="s-play-words"'));
        $this->assertStringContainsString('data-slow="'.e(__('pages.site.play_slow')).'"', $html);
    }

    public function test_the_api_the_rest_comes_from_answers_a_guest_for_an_open_song(): void
    {
        // The shape mixer.js reads: each part's key and its guide's notes.
        $parts = $this->getJson('/api/templates/amazing-grace')->assertOk()->json('data.template.parts');
        $this->assertIsArray($parts);
        $this->assertNotEmpty($parts);
        foreach ($parts as $part) {
            $this->assertIsString($part['key'] ?? null);
            $this->assertNotEmpty($part['guide']['notes'] ?? []);
        }
    }
}
