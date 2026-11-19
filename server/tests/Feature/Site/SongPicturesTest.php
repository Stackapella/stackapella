<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\SongCollections;
use App\Support\Pages\SongMedia;
use App\Support\Pages\SongPictures;
use App\Support\Pages\SongsHub;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Songs shown by their own pictures (Hillel, 2026-11-18: "pick an image from each song to use as the featured image when
 * showing the song on the category page, and then pick one song/image from the category page for the main songs page";
 * SongPictures, SongsHub::PICTURES), and /songs's song to try on staves ("let's show music staff notes by default").
 */
final class SongPicturesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    /**
     * What a page says it is in its alt text: music, words, a title page, a book, a manuscript, a letter, a map. A scene's
     * alt never opens with one of these (Hillel, 2026-11-18: "not sheet music or photos of pages").
     */
    private const PAGE = '/\\b(printed|page|pages|sheet[- ]music|music|score|staves|title|manuscript|handwritten|hymnal|hymn-?book|songbook|book|telegra(m|ph)|letter|newspaper|catalogue|map|chart|column|paragraph|text|cover|scroll)\\b/i';

    public function test_a_featured_picture_is_never_a_page(): void
    {
        foreach (SongPictures::SCENES as $slug => $names) {
            $records = SongMedia::records($slug);
            if ($records === []) {
                continue; // a song whose pictures aren't in this content yet
            }
            foreach ($names as $name) {
                if (! isset($records[$name]) && ! in_array('card', array_column($records, 'placement'), true)) {
                    continue; // its card picture is in a newer content commit than this one
                }
                $this->assertArrayHasKey($name, $records, "$slug: SCENES names picture $name, which it doesn't have");
                $alt = implode(' ', array_slice(explode(' ', (string) (($records[$name] ?? [])['alt']['en'] ?? '')), 0, 9));
                $this->assertDoesNotMatchRegularExpression(self::PAGE, $alt, "$slug $name is a page, not a place or a thing: $alt");
            }
        }
        foreach (SongPictures::FEATURED as $slug => $name) {
            $this->assertContains($name, SongPictures::SCENES[$slug], "$slug: its pick, $name, isn't a scene");
        }
        // Every open song's card shows a scene, or none.
        foreach (app(Catalogue::class)->openIndex() as $song) {
            $picture = SongPictures::featured($song['slug']);
            if ($picture !== null) {
                $this->assertContains($picture['name'], SongPictures::SCENES[$song['slug']] ?? [], $song['slug'].': its card shows a page');
            }
        }
    }

    public function test_the_story_hero_wins_then_the_pick_then_the_first_scene(): void
    {
        $choose = new ReflectionMethod(SongPictures::class, 'choose');
        $image = static fn (string $n, string $licence = 'PD-old'): array => ['src' => "/song-media/x/$n-960.webp", 'srcset' => "/song-media/x/$n-480.webp 480w, /song-media/x/$n-960.webp 960w, /song-media/x/$n-1440.webp 1440w", 'width' => 960, 'height' => 640, 'alt' => "Picture $n", 'licence' => $licence, 'credit' => "Credit $n", 'licenceName' => 'CC BY 4.0', 'licenceUrl' => 'https://creativecommons.org/licenses/by/4.0/'];
        // Simple Gifts: 1 is a page of music; 2, 3 and 4 are scenes, 3 the pick.
        $images = ['1' => $image('1'), '2' => $image('2'), '3' => $image('3'), '4' => $image('4', 'CC-BY-4.0')];

        $this->assertSame('4', $choose->invoke(null, 'simple-gifts', '4', $images)['name']);
        $this->assertSame('3', $choose->invoke(null, 'simple-gifts', '1', $images)['name'], 'a hero that is a page is passed over');
        $this->assertSame('3', $choose->invoke(null, 'simple-gifts', null, $images)['name']);
        $this->assertSame('2', $choose->invoke(null, 'simple-gifts', null, ['1' => $image('1'), '2' => $image('2')])['name']);
        // A song with only pages, or none listed, has none.
        $this->assertNull($choose->invoke(null, 'simple-gifts', null, ['1' => $image('1')]));
        $this->assertNull($choose->invoke(null, 'no-such-song', null, $images));
        // A public-domain picture carries no credit on its card (the song's page credits it); a CC BY one does.
        $this->assertNull($choose->invoke(null, 'simple-gifts', null, $images)['credit']);
        $this->assertSame('Credit 4', $choose->invoke(null, 'simple-gifts', '4', $images)['credit']);
        // Far from the frame's shape, it's shown whole.
        $this->assertTrue($choose->invoke(null, 'simple-gifts', null, ['2' => ['width' => 960, 'height' => 200] + $image('2')])['whole']);
        $this->assertNull($choose->invoke(null, 'simple-gifts', null, []));
    }

    public function test_each_card_on_a_collection_page_leads_with_its_picture(): void
    {
        $html = (string) $this->get('/songs/hymns')->assertOk()->getContent();
        foreach (app(SongCollections::class)->songs('hymns') as $card) {
            $picture = $card['picture'];
            if ($picture === null) {
                continue;
            }
            $this->assertStringContainsString('src="'.$picture['src'].'"', $html, $card['slug']);
            $this->assertStringContainsString('alt="'.e($picture['alt']).'"', $html, $card['slug']);
            $this->assertStringContainsString('width="'.$picture['width'].'" height="'.$picture['height'].'"', $html, $card['slug']);
        }
        $this->assertStringContainsString('class="s-xpic s-fpic', $html);
        $this->assertStringContainsString('loading="lazy" decoding="async"', $html);
        $this->assertStringContainsString('sizes="(min-width: 1200px) 370px', $html);
        // Its 480 and 960 px sizes only.
        $this->assertStringNotContainsString(' 1440w', $html);
    }

    public function test_a_song_without_a_place_or_thing_shows_its_opening_words(): void
    {
        $line = ['words' => [['beat' => 0, 'text' => 'Abide', 'joins' => false, 'br' => false], ['beat' => 1, 'text' => 'with', 'joins' => false, 'br' => false], ['beat' => 2, 'text' => 'me;', 'joins' => false, 'br' => false], ['beat' => 3, 'text' => 'fast', 'joins' => false, 'br' => true], ['beat' => 4, 'text' => 'falls', 'joins' => false, 'br' => false]]];
        $html = view('site._song-picture', ['picture' => null, 'song' => ['slug' => 'abide-with-me', 'title' => 'Abide with Me', 'line' => $line], 'tone' => 'hymns', 'class' => 's-xpic', 'sizes' => '100vw'])->render();

        $this->assertStringContainsString('s-fpic-words', $html);
        $this->assertStringContainsString('s-tone-hymns', $html);
        $this->assertStringContainsString('“Abide with me; fast falls”', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<svg', $html);
        // No words: its title.
        $this->assertStringContainsString('>Hum</span>', view('site._song-picture', ['picture' => null, 'song' => ['slug' => 'hum', 'title' => 'Hum', 'line' => null], 'class' => 's-xpic', 'sizes' => '100vw'])->render());
    }

    public function test_each_songs_panel_shows_a_songs_picture_and_the_try_is_on_staves(): void
    {
        $html = (string) $this->get('/songs')->assertOk()->getContent();
        $panels = array_values(array_filter(app(SongsHub::class)->bands(), static fn (array $band): bool => ! $band['season']));
        $this->assertNotSame([], $panels);
        foreach ($panels as $band) {
            $this->assertNotNull($band['picture'] ?? null, $band['key'].': its panel is headed by one of its songs');
            if ($band['picture']['picture'] !== null) {
                $this->assertStringContainsString('src="'.$band['picture']['picture']['src'].'"', $html);
                $this->assertContains($band['picture']['picture']['name'], SongPictures::SCENES[$band['picture']['slug']] ?? [], $band['key'].': its panel shows a page');
            }
            $this->assertStringContainsString('class="s-sh-pic-song" translate="no">'.e($band['picture']['title']).'</a>', $html);
            if (isset(SongsHub::PICTURES[$band['key']]) && in_array(SongsHub::PICTURES[$band['key']], app(SongCollections::class)->members($band['key']), true)) {
                $this->assertSame('/songs/'.SongsHub::PICTURES[$band['key']], $band['picture']['url']);
            }
        }
        // No drawn motif in the panels.
        $this->assertDoesNotMatchRegularExpression('#<section id="band-[a-z-]+" class="s-sh-panel[^>]*>\s*<svg#', $html);

        // The song to try: the song page's score card, turning at every width, every note in the page, Full score a link.
        $this->assertMatchesRegularExpression('#data-score data-slug="silent-night"\s+data-score-paged#', $html);
        $this->assertStringContainsString('data-score-song', $html);
        $this->assertStringContainsString('href="/songs/silent-night#song-full-score" class="s-score-full-open"', $html);
        $this->assertStringNotContainsString('s-score-dl', $html);
        $this->assertStringContainsString('src="/js/public/score-soon.js"', $html);
        $this->assertStringNotContainsString('s-channels', $html);
    }
}
