<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Flags;
use App\Support\Pages\SongScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A song page's score (docs/song-sheet-music-plan.md; Hillel's design, 2026-11-16): the hero's card holds the score's
 * room, a letter a staff to hear a part alone, the part picker at left and the downloads that start in the app; the
 * page says the score in words; and none of it is there where the song is withheld (geo rights).
 */
final class SongScoreTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $extra */
    private function song(string $slug = 'score-song', array $extra = []): Template
    {
        $template = Template::factory()->create(['slug' => $slug, 'title' => 'Score Song', 'access' => TemplateAccess::Plus]);
        $data = [...(array) TemplateVersion::factory()->make()->data, 'firstPublished' => ['year' => 1900, 'country' => 'US'], ...$extra];
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        return $template->refresh();
    }

    public function test_the_hero_holds_the_score_its_letters_the_picker_and_the_downloads(): void
    {
        $template = $this->song();
        $parts = array_column((array) $template->currentVersion->data['parts'], 'key');

        $page = $this->get('/songs/score-song')->assertOk();
        $html = (string) $page->getContent();

        $this->assertStringContainsString('data-score-view', $html);
        $this->assertStringContainsString('src="/js/public/song-page.js"', $html);
        $this->assertStringContainsString('id="song-mixer" data-player', $html);
        foreach ($parts as $key) {
            $this->assertMatchesRegularExpression('/data-voice="'.$key.'"[^>]*>\s*<button[^>]*data-hear-alone/', $html, "a letter for $key");
            $this->assertStringContainsString('data-pick="'.$key.'"', $html);
        }
        // One part shows chosen, the one the player sings forward.
        $this->assertSame(1, substr_count($html, 'role="radio" aria-checked="true"'));
        $score = $page->viewData('score');
        $this->assertSame(SongScore::FILES, array_keys($score['downloads']));
        foreach ($score['downloads'] as $file => $url) {
            $this->assertStringContainsString('/t/score-song/sheet?', $url);
            $this->assertStringContainsString('get='.$file, $url);
            $this->assertStringContainsString(e($url), $html);
        }
        $this->assertStringContainsString('free with an account', $html);
        // Its notes are in the page, every one, so the score costs no read of the API (the coordinator, 2026-11-16).
        $this->assertSame(1, preg_match('/<script type="application\/json" data-score-song>(.*?)<\/script>/s', $html, $found));
        $embedded = json_decode($found[1], true);
        $data = (array) $template->currentVersion->data;
        $this->assertSame($data['key'], $embedded['key']);
        $this->assertSame($data['timeSignature'], $embedded['timeSignature']);
        $this->assertSame($data['genre'] ?? null, $embedded['genre'] ?? null, 'its genres, so a round is written as one');
        foreach ($data['parts'] as $i => $part) {
            $this->assertSame($part['key'], $embedded['parts'][$i]['key']);
            $this->assertCount(count($part['guide']['notes']), $embedded['parts'][$i]['guide']['notes']);
        }
        $this->assertArrayNotHasKey('licence', $embedded);
        // The score in words, and the full score's place.
        $this->assertStringContainsString('The sheet music for Score Song', $html);
        $this->assertStringContainsString('id="song-full-score"', $html);
        $this->assertStringContainsString('CC BY-SA 4.0', $html);
        // And search is told: the composition has its sheet music, free to read, under the content licence.
        $this->assertStringContainsString('"@type":"SheetMusic"', $html);
        $this->assertMatchesRegularExpression('/"name":"Score Song: free [^"]*sheet music/', $html);
    }

    public function test_the_bars_are_counted_as_the_engraver_numbers_them(): void
    {
        $notes = static fn (float $end): array => ['parts' => [['guide' => ['notes' => [['beat' => 0, 'length' => $end]]]]]];

        $this->assertSame(16, SongScore::bars([...$notes(49.0), 'pickupBeats' => 1], '3/4'), 'an upbeat is not a bar');
        $this->assertSame(8, SongScore::bars($notes(32.0), '4/4'));
        $this->assertSame(9, SongScore::bars($notes(32.5), '4/4'), 'a bar begun is a bar');
        $this->assertSame(8, SongScore::bars($notes(16.0), '6/8'), 'a 6/8 bar is two beats');
        $this->assertNull(SongScore::bars([...$notes(32.0), 'meters' => [['beat' => 8, 'timeSignature' => '3/4']]], '4/4'), 'a changing metre has no one count');
    }

    public function test_no_score_no_letters_and_no_downloads_where_the_song_is_withheld(): void
    {
        $this->song('held-song', ['makers' => [['name' => 'A Composer', 'role' => 'music', 'died' => 1850], ['name' => 'A Poet', 'role' => 'words', 'died' => 1960]]]);
        config(['proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true]);
        app(Flags::class)->set('geo_rights', 'on', null, 'test');
        $visitor = static fn (string $country): array => ['do-connecting-ip' => '172.70.1.2', 'cf-connecting-ip' => '198.51.100.7', 'cf-ipcountry' => $country];

        $mexico = $this->withHeaders($visitor('MX'))->get('/songs/held-song')->assertOk();
        $html = (string) $mexico->getContent();
        $this->assertNull($mexico->viewData('score'));
        foreach (['data-score', 'SheetMusic', 'data-score-song', '"pitch"', 'data-hear-alone', 'data-pick=', 'get=pdf', 'get=musicxml', 'get=midi', 'id="song-full-score"', 'The sheet music for'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, "$gone is not on a withheld page");
        }

        $us = $this->withHeaders($visitor('US'))->get('/songs/held-song')->assertOk();
        $this->assertNotNull($us->viewData('score'));
        $this->assertStringContainsString('data-score-view', (string) $us->getContent());
        $this->assertStringContainsString('data-score-song', (string) $us->getContent());
    }

    public function test_the_spanish_page_says_it_in_spanish(): void
    {
        $this->song();

        $html = (string) $this->get('/es/songs/score-song')->assertOk()->getContent();
        $this->assertStringContainsString('¿Qué voz cantas?', $html);
        $this->assertStringContainsString('gratis con una cuenta', $html);
        $this->assertStringContainsString('Partitura completa', $html);
    }
}
