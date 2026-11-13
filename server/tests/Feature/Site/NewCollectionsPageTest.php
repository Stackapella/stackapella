<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Pages\Motif;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongsHub;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The Scottish songs and the villancicos (Hillel's new collections, 2026-11-12; the demand check): each a collection page
 * once four of its songs are open, in its own tone with its whole-object motif (the thistle, the star lantern), a panel on
 * /songs, and the villancicos leading the Spanish /songs in the carol season. Songs are tagged here as the arranger's
 * files will tag them ("scottish", "villancico"), on songs that are open in the test catalogue.
 */
final class NewCollectionsPageTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_neither_is_a_page_until_four_of_its_songs_are_open(): void
    {
        // The content tags its Scottish songs and villancicos now: start with none, then two Scottish.
        $this->untag('scottish');
        $this->untag('villancico');
        $this->tag(['auld-lang-syne', 'loch-lomond'], 'scottish');
        // Until then a Scottish song stays where it was: Folk songs lists it.
        $this->assertStringContainsString('href="/songs/loch-lomond"', (string) $this->get('/songs/folk-songs')->assertOk()->getContent());

        $this->get('/songs/scottish-songs')->assertNotFound();
        $this->get('/songs/villancicos')->assertNotFound();
        $this->get('/songs')->assertOk()->assertDontSee('href="/songs/scottish-songs"', false)->assertDontSee('href="/songs/villancicos"', false);
    }

    public function test_the_scottish_page_lists_its_songs_in_its_tone_with_its_thistle_and_folk_keeps_its_own(): void
    {
        $songs = ['auld-lang-syne', 'loch-lomond', 'the-water-is-wide', 'danny-boy'];
        $this->tag($songs, 'scottish');

        $html = (string) $this->get('/songs/scottish-songs')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Scottish Songs for Choir: Auld Lang Syne and More</title>', $html);
        $this->assertStringContainsString('class="s-xhero s-tone s-tone-scottish-songs"', $html);
        $this->assertStringContainsString('Robert Burns wrote its words in 1788', $html);
        // The thistle, both skins' drawings, its ids its own.
        $this->assertStringContainsString('class="s-tone-motif s-obj"', $html);
        $this->assertStringContainsString('class="s-obj-serious"', $html);
        $this->assertStringContainsString('class="s-obj-game"', $html);
        $this->assertStringContainsString('id="scottish-songs-', $html);
        foreach ($songs as $slug) {
            $this->assertStringContainsString('href="/songs/'.$slug.'"', $html, $slug);
        }

        $this->get('/songs')->assertOk()->assertSee('href="/songs/scottish-songs"', false)->assertSee(e(__('site.collections.scottish-songs.band')), false);
        $this->assertStringContainsString('/songs/scottish-songs<', (string) $this->get('/sitemap.xml')->getContent());

        // The Scottish songs are folk songs too: while the Scottish page is open it's their group and their home, so Folk
        // songs no longer lists them.
        $this->assertStringNotContainsString('href="/songs/loch-lomond"', (string) $this->get('/songs/folk-songs')->assertOk()->getContent());
        $this->assertStringContainsString('href="/songs/scottish-songs"', (string) $this->get('/songs/loch-lomond')->assertOk()->getContent());
    }

    public function test_the_villancicos_page_is_its_songs_home_and_keeps_its_panel_through_the_carol_season(): void
    {
        $this->travelTo('2026-12-01 12:00:00');
        $songs = ['silent-night', 'jingle-bells', 'hark-the-herald', 'shchedryk'];
        $this->tag($songs, 'villancico');

        $html = (string) $this->get('/songs/villancicos')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Villancicos: Spanish Christmas Carols for Choir</title>', $html);
        $this->assertStringContainsString('class="s-xhero s-tone s-tone-villancicos"', $html);
        $this->assertStringContainsString('id="villancicos-', $html);

        // A villancico's trail leads to its own page, ahead of the carols page.
        $this->assertStringContainsString('href="/songs/villancicos"', (string) $this->get('/songs/silent-night')->assertOk()->getContent());

        // In the carol season its panel keeps its songs (a collection's carols are otherwise the carols band's)...
        $bands = collect(app(SongsHub::class)->bands())->keyBy('key');
        $this->assertCount(4, $bands['villancicos']['samples'] ?? []);
        $this->assertNotSame('villancicos', collect(app(SongsHub::class)->bands())->firstWhere('season', false)['key'] ?? null);

        // ...and on the Spanish site it's the first panel.
        app()->setLocale('es');
        $this->assertSame('villancicos', collect(app(SongsHub::class)->bands())->firstWhere('season', false)['key'] ?? null);
    }

    public function test_the_villancicos_intro_names_millets_harmony_only_for_a_song_its_page_lists(): void
    {
        $intro = implode(' ', (array) __('site.collections.villancicos.intro'));
        $this->assertStringContainsString('Lluís Millet', $intro);
        // While the page is open, El cant dels ocells is on it, on Millet's own harmonisation as its credit says (the
        // arranger, 2026-11-12); before that the page isn't shown at all.
        $members = app(SongCollections::class)->members('villancicos');
        if ($members !== []) {
            $this->assertContains('el-cant-dels-ocells', $members);
            $credit = (string) (((array) (((array) Template::query()->with('currentVersion')->where('slug', 'el-cant-dels-ocells')->firstOrFail()->currentVersion?->data)['credits'] ?? []))['arrangement'] ?? '');
            $this->assertStringContainsString('Millet', $credit);
        }
        $this->assertNotContains('fum-fum-fum', SongsHub::SAMPLES['villancicos']);
    }

    public function test_the_thanksgiving_hymns_page_is_its_hymns_home_and_the_hymns_page_keeps_them(): void
    {
        $songs = ['abide-with-me', 'amazing-grace', 'it-is-well', 'ode-to-joy'];
        $this->tag($songs, 'thanksgiving');

        $html = (string) $this->get('/songs/thanksgiving-hymns')->assertOk()->getContent();
        $this->assertStringContainsString('<title>'.e(SitePages::title('Thanksgiving and Harvest Hymns for Choir')).'</title>', $html);
        $this->assertStringContainsString('class="s-xhero s-tone s-tone-thanksgiving-hymns"', $html);
        $this->assertStringContainsString('id="thanksgiving-hymns-', $html);
        $this->assertStringNotContainsString('thanksgiving-songs', $html);
        foreach ($songs as $slug) {
            $this->assertStringContainsString('href="/songs/'.$slug.'"', $html, $slug);
        }
        // Still hymns: the hymns page lists them, and their own page is their trail's.
        $hymns = (string) $this->get('/songs/hymns')->assertOk()->getContent();
        $this->assertStringContainsString('href="/songs/amazing-grace"', $hymns);
        $this->assertStringContainsString('href="/songs/thanksgiving-hymns"', (string) $this->get('/songs/amazing-grace')->assertOk()->getContent());
    }

    public function test_the_thanksgiving_intro_names_every_hymn_and_the_printed_four_parts_it_keeps(): void
    {
        // As the Jewish songs' intro is checked: every hymn the page lists keeps a hymnal's four parts (its credit is
        // someone's harmony, adapted by Stackapella, never "Stackapella" alone), and the intro names it. Before the
        // songs open the page lists none, and the intro still says what its four will keep.
        $intro = implode(' ', (array) __('site.collections.thanksgiving-hymns.intro'));
        $this->assertStringContainsString('adapted by us', $intro);
        foreach (app(SongCollections::class)->members('thanksgiving-hymns') as $slug) {
            $template = Template::query()->with('currentVersion')->where('slug', $slug)->firstOrFail();
            $credit = (string) (((array) (((array) $template->currentVersion?->data)['credits'] ?? []))['arrangement'] ?? '');
            $this->assertNotSame('Stackapella', trim($credit), $slug.' is our own arrangement, which the intro would have to say');
            $this->assertStringContainsString('adapted by Stackapella', $credit, $slug);
            $this->assertStringContainsString((string) $template->title, $intro, $slug);
        }
    }

    public function test_the_shaker_page_leads_with_simple_gifts_and_is_its_home(): void
    {
        // Simple Gifts is listed by name (its file says folk and hymn); three more songs tagged shaker open the page.
        $songs = ['the-water-is-wide', 'shenandoah', 'deep-river'];
        $this->tag($songs, 'shaker');

        $html = (string) $this->get('/songs/shaker-songs')->assertOk()->getContent();
        $this->assertStringContainsString('<title>'.e(SitePages::title('Shaker Songs for Choir: Simple Gifts and More')).'</title>', $html);
        $this->assertStringContainsString('class="s-xhero s-tone s-tone-shaker-songs"', $html);
        $this->assertStringContainsString('id="shaker-songs-', $html);
        foreach (['simple-gifts', ...$songs] as $slug) {
            $this->assertStringContainsString('href="/songs/'.$slug.'"', $html, $slug);
        }
        // Simple Gifts' own page leads to it, and the hymns page still lists it.
        $this->assertStringContainsString('href="/songs/shaker-songs"', (string) $this->get('/songs/simple-gifts')->assertOk()->getContent());
        $this->assertStringContainsString('href="/songs/simple-gifts"', (string) $this->get('/songs/hymns')->assertOk()->getContent());
        $this->assertSame('simple-gifts', collect(app(SongsHub::class)->bands())->firstWhere('key', 'shaker-songs')['samples'][0]['slug'] ?? null);
    }

    public function test_the_shaker_intro_is_true_of_every_song_its_page_lists(): void
    {
        // "Every song here is ... our own arrangement", and every song but Simple Gifts is from Mary Hazzard's songbook.
        $intro = implode(' ', (array) __('site.collections.shaker-songs.intro'));
        $this->assertStringContainsString('our own arrangement', $intro);
        foreach (app(SongCollections::class)->members('shaker-songs') as $slug) {
            $credits = (array) (((array) Template::query()->with('currentVersion')->where('slug', $slug)->firstOrFail()->currentVersion?->data)['credits'] ?? []);
            $this->assertSame('Stackapella', trim((string) ($credits['arrangement'] ?? '')), $slug);
            if ($slug !== 'simple-gifts') {
                $this->assertStringContainsString('Hazzard', (string) ($credits['words'] ?? ''), $slug);
            }
        }
    }

    public function test_a_motif_is_drawn_only_for_a_collection_that_has_one(): void
    {
        $this->assertSame('', (string) Motif::svg('hymns', 'x'));
        $this->assertStringNotContainsString('s-obj-game', (string) Motif::svg('villancicos', 'mo', false));
        $this->assertMatchesRegularExpression('#^<svg class="mo" viewBox="0 0 150 152" style="aspect-ratio: 150 / 152"#', (string) Motif::svg('villancicos', 'mo', false));
    }

    /** Takes a genre off every song, so a test counts only the songs it tags itself. */
    private function untag(string $genre): void
    {
        foreach (Template::query()->with('currentVersion')->get() as $template) {
            $data = (array) $template->currentVersion?->data;
            $genres = array_values(array_filter((array) ($data['genre'] ?? []), 'is_string'));
            if (! in_array($genre, $genres, true)) {
                continue;
            }
            $data['genre'] = array_values(array_diff($genres, [$genre]));
            DB::table('template_versions')->where('id', $template->current_version_id)->update(['data' => json_encode($data)]);
        }
    }

    /**
     * Gives these songs a genre in their current version, as their files would.
     *
     * @param  list<string>  $slugs
     */
    private function tag(array $slugs, string $genre): void
    {
        foreach (Template::query()->with('currentVersion')->whereIn('slug', $slugs)->get() as $template) {
            $data = (array) $template->currentVersion?->data;
            $data['genre'] = [...array_values(array_filter((array) ($data['genre'] ?? []), 'is_string')), $genre];
            DB::table('template_versions')->where('id', $template->current_version_id)->update(['data' => json_encode($data)]);
        }
        $this->assertCount(count($slugs), Template::query()->whereIn('slug', $slugs)->get(), 'every song exists in the test catalogue');
    }
}
