<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\Carols;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongParts;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The song collections (/songs/<collection>, SongCollections): each open song of the collection's genres as a card
 * with its part pages, an ItemList and a trail; in the sitemap and linked from /songs; never thin (a 404 under
 * SongCollections::MIN open songs); a song from another genre stays off.
 */
final class SongCollectionsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_the_hymns_page_lists_every_open_hymn_with_its_parts(): void
    {
        $hymns = app(SongCollections::class)->songs('hymns');
        $this->assertGreaterThanOrEqual(SongCollections::MIN, count($hymns));

        $page = $this->get('/songs/hymns')->assertOk()
            ->assertSee(__('site.collections.hymns.headline'))
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('data-songbook', false);
        foreach ($hymns as $hymn) {
            $page->assertSee(e($hymn['title']), false);
            foreach ($hymn['parts'] as $part) {
                $page->assertSee('href="'.$part['url'].'"', false);
            }
        }
        // A song with no hymn in its genre stays off.
        $this->assertNotContains('danny-boy', array_column($hymns, 'slug'));
        $page->assertDontSee('href="/songs/danny-boy"', false);
    }

    public function test_every_listed_hymn_has_its_tune_in_the_soprano_as_the_intro_says(): void
    {
        // The intro says every hymn here has the tune in the soprano (the courses lead's caution): a hymn whose tune is
        // in another part must change the words before it joins.
        foreach (app(SongCollections::class)->songs('hymns') as $hymn) {
            $melody = array_values(array_filter($hymn['parts'], static fn (array $part): bool => $part['melody']));
            $this->assertSame(['soprano'], array_column($melody, 'key'), $hymn['slug']);
            // And SATB, as the voicings guide says ("Every one of the hymns is SATB"; the courses lead, 2026-11-10).
            $this->assertSame(SongParts::SATB, array_column($hymn['parts'], 'key'), $hymn['slug']);
        }
    }

    public function test_it_is_in_the_sitemap_and_linked_from_songs(): void
    {
        $this->assertStringContainsString('/songs/hymns<', (string) $this->get('/sitemap.xml')->getContent());
        $this->get('/songs')->assertOk()->assertSee('href="/songs/hymns"', false)->assertSee(__('site.collections.hymns.songs_link'));
    }

    public function test_songs_lists_every_open_song_once_in_its_group(): void
    {
        $this->travelTo('2027-03-01 12:00:00');
        $html = (string) $this->get('/songs')->assertOk()->getContent();
        $open = app(Catalogue::class)->open();

        // Each open song once, in the group its genres give it, and every group in order (Christmas last out of season).
        foreach ($open as $template) {
            $this->assertSame(1, substr_count($html, 'href="/songs/'.$template->slug.'" class="s-song"'), $template->slug);
        }
        preg_match_all('#id="songs-([a-z-]+)-h"#', $html, $m);
        $order = [...array_values(array_diff(array_keys(SongCollections::GROUPS), ['christmas'])), 'christmas', 'more'];
        $this->assertSame(array_values(array_intersect($order, $m[1])), $m[1]);
        $this->assertSame('christmas', end($m[1]) === 'more' ? $m[1][count($m[1]) - 2] : end($m[1]));
        // The carols page is linked only in its season.
        $this->assertStringNotContainsString('href="/songs/christmas-carols"', $html);

        $this->travelTo('2026-11-10 12:00:00');
        $html = (string) $this->get('/songs')->assertOk()->getContent();
        preg_match_all('#id="songs-([a-z-]+)-h"#', $html, $m);
        $this->assertSame('christmas', $m[1][0]);
        $this->assertStringContainsString('href="/songs/christmas-carols"', $html);
    }

    public function test_a_song_goes_to_its_first_group_in_order(): void
    {
        $this->assertSame('christmas', SongCollections::group(['hymn', 'christmas']));
        $this->assertSame('rounds', SongCollections::group(['round', "children's", 'folk']));
        $this->assertSame('spirituals', SongCollections::group(['spiritual', 'gospel']));
        $this->assertSame('hymns', SongCollections::group(['gospel', 'hymn', 'american']));
        $this->assertSame('sea-shanties', SongCollections::group(['folk', 'sea shanty']));
        $this->assertSame('spirituals', SongCollections::group(['spiritual', 'folk']));
        $this->assertSame('folk', SongCollections::group(['folk', 'traditional']));
        $this->assertSame('more', SongCollections::group(['celebration-unknown']));
    }

    /** @return array<string, array{string, string}> */
    public static function collections(): array
    {
        return ['spirituals' => ['spirituals', 'spiritual'], 'sea shanties' => ['sea-shanties', 'sea shanty'], 'rounds' => ['rounds', 'round']];
    }

    #[DataProvider('collections')]
    public function test_each_collection_lists_its_open_songs_with_their_parts(string $slug, string $genre): void
    {
        $songs = app(SongCollections::class)->songs($slug);
        $this->assertGreaterThanOrEqual(SongCollections::MIN, count($songs));

        $page = $this->get(SongCollections::path($slug))->assertOk()
            ->assertSee(__('site.collections.'.$slug.'.headline'))
            ->assertSee('"@type":"ItemList"', false);
        foreach ($songs as $song) {
            $page->assertSee(e($song['title']), false);
            foreach ($song['parts'] as $part) {
                $page->assertSee('href="'.$part['url'].'"', false);
            }
        }
        foreach (app(Catalogue::class)->open() as $template) {
            $in = in_array($genre, array_map('strtolower', (array) (((array) $template->currentVersion?->data)['genre'] ?? [])), true);
            $this->assertSame($in && SongParts::hasPages($template), in_array($template->slug, array_column($songs, 'slug'), true), $template->slug);
        }
        $this->get('/songs')->assertSee('href="'.SongCollections::path($slug).'"', false);
    }

    public function test_the_spirituals_intro_names_deep_river_only_while_it_is_burleighs_setting(): void
    {
        // The intro says Deep River is Burleigh's own 1914 setting (the courses lead's check): its credits must say so.
        $deep = Template::query()->with('currentVersion')->where('slug', 'deep-river')->first();
        $this->assertStringContainsString('Deep River', implode(' ', (array) __('site.collections.spirituals.intro')));
        $this->assertNotNull($deep);
        $this->assertStringContainsString('Burleigh', (string) json_encode(((array) $deep->currentVersion?->data)['credits'] ?? []));
    }

    public function test_a_round_card_tags_no_one_voice_the_melody_and_drops_its_round_from_the_title(): void
    {
        $rounds = app(SongCollections::class)->songs('rounds');
        $this->assertNotSame([], $rounds);
        foreach ($rounds as $round) {
            $this->assertSame([], array_filter(array_column($round['parts'], 'melody')), $round['slug']);
            $this->assertStringNotContainsStringIgnoringCase('(round)', $round['title'], $round['slug']);
        }
        // A song that isn't a round keeps its melody tag.
        $hymn = app(SongCollections::class)->songs('hymns')[0];
        $this->assertContains(true, array_column($hymn['parts'], 'melody'));
    }

    public function test_every_other_spiritual_is_our_own_arrangement_as_the_intro_says(): void
    {
        // Go Down, Moses keeps Seward's 1872 answer, as the intro says (the courses lead, 2026-11-10).
        $moses = Template::query()->with('currentVersion')->where('slug', 'go-down-moses')->firstOrFail();
        $this->assertStringContainsString('Seward', (string) (((array) (((array) $moses->currentVersion?->data)['credits'] ?? []))['arrangement'] ?? ''));
        $this->assertStringContainsString('Seward', implode(' ', (array) __('site.collections.spirituals.intro')));
        // The intro names Deep River as Burleigh's setting and There Is a Balm in Gilead as Dett's four parts (the arranger,
        // 2026-11-11), and says the rest are our own arrangements: another arranger's setting joining must change the words first.
        foreach (app(SongCollections::class)->songs('spirituals') as $song) {
            if ($song['slug'] === 'deep-river') {
                continue;
            }
            if ($song['slug'] === 'balm-in-gilead') {
                $balm = Template::query()->with('currentVersion')->where('slug', 'balm-in-gilead')->firstOrFail();
                $this->assertStringContainsString('Dett', (string) (((array) (((array) $balm->currentVersion?->data)['credits'] ?? []))['arrangement'] ?? ''));
                $this->assertStringContainsString('Dett', implode(' ', (array) __('site.collections.spirituals.intro')));

                continue;
            }
            $template = Template::query()->with('currentVersion')->where('slug', $song['slug'])->firstOrFail();
            $this->assertStringStartsWith('Stackapella', (string) (((array) (((array) $template->currentVersion?->data)['credits'] ?? []))['arrangement'] ?? ''), $song['slug']);
        }
    }

    public function test_the_rounds_intro_names_the_oldest_round_it_lists(): void
    {
        // "the oldest in Thomas Ravenscroft's Pammelia of 1609" (Hey, Ho, Nobody Home): it must be open and listed.
        $this->assertStringContainsString('1609', implode(' ', (array) __('site.collections.rounds.intro')));
        $this->assertContains('hey-ho-nobody-home', array_column(app(SongCollections::class)->songs('rounds'), 'slug'));
    }

    public function test_a_song_and_its_part_pages_link_the_page_that_collects_it_in_the_trail_and_below(): void
    {
        $this->travelTo('2027-03-01 12:00:00');
        $hymn = app(SongCollections::class)->songs('hymns')[0];
        $trail = '#<a href="/songs/hymns"\s*>'.preg_quote(e(__('site.collections.hymns.crumb')), '#').'</a>#';
        $ld = '"name":"'.__('site.collections.hymns.crumb').'","item":"'.Hosts::publicUrl().'/songs/hymns"';
        $more = e(__('site.collections.hymns.more'));

        foreach (['/songs/'.$hymn['slug'], $hymn['parts'][1]['url']] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression($trail, $html, $url);
            $this->assertStringContainsString($ld, $html, $url);
            $this->assertStringContainsString($more, $html, $url);
        }

        // A round goes to the rounds page; a song no page collects has neither.
        $round = app(SongCollections::class)->songs('rounds')[0];
        $this->assertMatchesRegularExpression('#<a href="/songs/rounds"\s*>#', (string) $this->get('/songs/'.$round['slug'])->getContent());
        $this->assertNull(app(SongCollections::class)->home('danny-boy'));
        $this->get('/songs/danny-boy')->assertOk()->assertDontSee('class="s-carols-link"', false);
    }

    public function test_a_carol_points_to_the_carols_page_in_its_season_and_to_its_other_page_after(): void
    {
        $carol = app(Carols::class)->songs();
        $this->assertNotSame([], $carol);
        $slug = $carol[0]['slug'];

        $this->travelTo('2026-12-01 12:00:00');
        $this->get('/songs/'.$slug.'/alto')->assertOk()->assertSee('<a href="/songs/christmas-carols">', false)->assertSee(e(__('site.carols.links.part')), false)
            ->assertSee('"item":"'.Hosts::publicUrl().'/songs/christmas-carols"', false);

        $this->travelTo('2027-03-01 12:00:00');
        $this->get('/songs/'.$slug.'/alto')->assertOk()->assertDontSee('<a href="/songs/christmas-carols">', false);
    }

    public function test_the_guides_link_the_collections_that_fit(): void
    {
        foreach (['satb-explained' => ['hymns'], 'choir-voicings' => ['hymns'], 'a-round-for-beginners' => ['rounds'], 'practise-a-choir-part' => ['hymns', 'spirituals'], 'what-is-a-descant' => ['hymns']] as $guide => $collections) {
            $page = $this->get('/guides/'.$guide)->assertOk();
            foreach ($collections as $collection) {
                $page->assertSee('href="/songs/'.$collection.'"', false);
                $this->assertNotSame([], app(SongCollections::class)->members($collection), $collection);
            }
        }
    }

    public function test_with_too_few_open_hymns_it_is_not_a_page(): void
    {
        $hymns = Template::query()->listed()->with('currentVersion')->get()
            ->filter(static fn (Template $t): bool => in_array('hymn', array_map('strtolower', (array) (((array) $t->currentVersion?->data)['genre'] ?? [])), true));
        // Leave fewer than the minimum open.
        $keep = $hymns->filter(static fn (Template $t): bool => SongParts::hasPages($t))->take(SongCollections::MIN - 1)->pluck('id');
        DB::table('template_versions')->whereIn('id', $hymns->reject(static fn (Template $t): bool => $keep->contains($t->id))->pluck('current_version_id'))->update(['reviewed' => false]);

        $this->get('/songs/hymns')->assertNotFound();
        $this->assertStringNotContainsString('/songs/hymns<', (string) $this->get('/sitemap.xml')->getContent());
        $this->get('/songs')->assertOk()->assertDontSee('href="/songs/hymns"', false);
    }
}
