<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\Carols;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongParts;
use App\Support\Pages\SongsHub;
use App\Support\Site\LinkCardLanes;
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
        $this->get('/songs')->assertOk()->assertSee('href="/songs/hymns"', false)->assertSee(__('site.collections.hymns.crumb'));
    }

    public function test_songs_samples_each_collection_and_lists_every_open_song_a_to_z(): void
    {
        $this->travelTo('2027-03-01 12:00:00');
        $html = (string) $this->get('/songs')->assertOk()->getContent();
        $open = app(Catalogue::class)->open();

        // Every open song once in the A to Z, so none is more than a click from /songs (Hillel's sampler, 2026-11-11).
        preg_match('#<div class="s-sh-letters">(.*?)<p class="s-sh-none"#s', $html, $az);
        foreach ($open as $template) {
            $this->assertSame(1, substr_count($az[1] ?? '', 'href="/songs/'.$template->slug.'"'), $template->slug);
        }
        // Each entry carries the collections that list it, for the filters (Hillel's songbook design).
        $this->assertMatchesRegularExpression('#<li data-in="[a-z -]+" data-title="amazing grace"#', $az[1] ?? '');
        // Out of the carol season: no carols band, and the collections' panels the biggest first.
        preg_match_all('#<section id="band-([a-z-]+)"#', $html, $m);
        $this->assertNotContains('christmas', $m[1]);
        $sizes = array_map(static fn (string $slug): int => count(app(SongCollections::class)->members($slug)), $m[1]);
        $sorted = $sizes;
        rsort($sorted);
        $this->assertSame($sorted, $sizes);
        $this->assertStringNotContainsString('href="/songs/christmas-carols"', $html);

        // In season the carols come first, as the full-width band, until Twelfth Night.
        $this->travelTo('2026-11-10 12:00:00');
        $html = (string) $this->get('/songs')->assertOk()->getContent();
        preg_match_all('#<section id="band-([a-z-]+)"#', $html, $m);
        if (app(Carols::class)->songs() !== []) {
            $this->assertSame('christmas', $m[1][0]);
            $this->assertStringContainsString('s-sh-season', $html);
            $this->assertStringContainsString(__('pages.songs.season_until', ['date' => '6 January']), $html);
        }
    }

    public function test_the_songbook_searches_without_the_script_and_offers_a_song_to_try(): void
    {
        // ?q= narrows the A to Z on the server (the hero's search when the script hasn't run), the page staying /songs.
        $html = (string) $this->get('/songs?q=grace')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<li data-in="[a-z -]+" data-title="amazing grace" >#', $html);
        $this->assertMatchesRegularExpression('#<li data-in="[a-z -]+" data-title="silent night"\s+hidden\s*>#', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.Hosts::publicUrl().'/songs">', $html);
        // A song to try in the hero: its parts, each switchable (x-site.mixer).
        $this->assertStringContainsString('id="try-mixer"', $html);
        $this->assertStringContainsString('data-toggle', $html);
    }

    public function test_each_collection_offers_its_link_cards_song_to_try_with_that_cards_part_lit(): void
    {
        $seen = 0;
        foreach (['hymns', 'spirituals', 'sea-shanties', 'rounds', 'folk-songs', 'barbershop-songs'] as $collection) {
            [$slug, $part] = LinkCardLanes::SONGS[$collection];
            if (app(SongCollections::class)->songs($collection) === [] || ! Template::query()->where('slug', $slug)->exists()) {
                continue;
            }
            $html = (string) $this->get('/songs/'.$collection)->assertOk()->getContent();

            $this->assertStringContainsString('s-xcoll-has-try', $html, $collection);
            $this->assertMatchesRegularExpression('#<div class="s-sh-try s-xcoll-try">\s*<div class="s-mixer" id="try-mixer"#', $html, $collection);
            preg_match('#<script type="application/json" data-song>(.*?)</script>#s', $html, $m);
            $song = (array) json_decode($m[1] ?? '{}', true);
            $this->assertSame([$slug, $part], [$song['song'] ?? null, $song['you'] ?? null], $collection);
            $seen++;
        }
        $this->assertGreaterThan(0, $seen);
        // The Jewish page keeps its countdown there instead.
        $this->get('/songs/jewish-songs')->assertDontSee('s-xcoll-has-try', false);
    }

    public function test_every_open_part_fits_the_songbooks_one_scale_and_each_card_draws_its_range_on_it(): void
    {
        // One scale on every page (the UX review of Hillel's Jewish Songs redesign): a part outside it would draw past
        // its track, so widen Carols::SCALE (and the caption's words follow) when a new song needs it.
        [$low, $high] = Carols::SCALE;
        foreach (Template::query()->with('currentVersion')->get() as $template) {
            if (! Catalogue::isOpen($template)) {
                continue;
            }
            foreach (SongParts::parts($template) as $part) {
                $this->assertGreaterThanOrEqual($low, $part['low'], $template->slug.'/'.$part['key']);
                $this->assertLessThanOrEqual($high, $part['high'], $template->slug.'/'.$part['key']);
            }
        }

        $html = (string) $this->get('/songs/hymns')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span class="s-xbar" aria-hidden="true"><i style="--lo: [\d.]+%; --hi: [\d.]+%"></i></span>#', $html);
        $this->assertStringContainsString('Bars show each part on one scale, E2 to G5. The line is middle C.', $html);
        // At most three across (Hillel); the hymns' last card isn't left alone when their count leaves one over.
        $this->assertStringNotContainsString('s-xcards-4', $html);
        preg_match_all('#<article id="carol-#', $html, $cards);
        $this->assertStringContainsString(count($cards[0]) >= 4 && count($cards[0]) % 3 === 1 ? 'class="s-xcards s-xcards-tail"' : 'class="s-xcards"', $html);
    }

    public function test_a_song_goes_to_its_first_group_in_order(): void
    {
        $this->assertSame('christmas', SongCollections::group(['hymn', 'christmas']));
        $this->assertSame('rounds', SongCollections::group(['round', "children's", 'folk']));
        $this->assertSame('spirituals', SongCollections::group(['spiritual', 'gospel']));
        $this->assertSame('hymns', SongCollections::group(['gospel', 'hymn', 'american']));
        $this->assertSame('sea-shanties', SongCollections::group(['folk', 'sea shanty']));
        $this->assertSame('spirituals', SongCollections::group(['spiritual', 'folk']));
        $this->assertSame('folk-songs', SongCollections::group(['folk', 'traditional']));
        $this->assertSame('more', SongCollections::group(['celebration-unknown']));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function collections(): array
    {
        return ['spirituals' => ['spirituals', ['spiritual']], 'sea shanties' => ['sea-shanties', ['sea shanty']], 'rounds' => ['rounds', ['round']],
            'folk songs' => ['folk-songs', ['folk']], 'barbershop and parlour' => ['barbershop-songs', ['barbershop', 'parlour song', 'early popular song']]];
    }

    /** @param  list<string>  $genres */
    #[DataProvider('collections')]
    public function test_each_collection_lists_its_open_songs_with_their_parts(string $slug, array $genres): void
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
            $own = array_values(array_map('strtolower', array_filter((array) (((array) $template->currentVersion?->data)['genre'] ?? []), 'is_string')));
            // Folk and barbershop take only what /songs groups under them: a shanty or a round tagged folk keeps its own page.
            $in = array_intersect($genres, $own) !== [] && (! in_array($slug, SongCollections::OWN_GROUP_ONLY, true) || SongCollections::group($own) === $slug);
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
        $this->assertNull(app(SongCollections::class)->home('happy-birthday'));
        $this->get('/songs/happy-birthday')->assertOk()->assertDontSee('class="s-carols-link"', false);
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

    public function test_the_folk_and_barbershop_intros_name_exactly_the_songs_their_pages_list(): void
    {
        foreach (['folk-songs' => 20, 'barbershop-songs' => 5] as $slug => $count) {
            $songs = app(SongCollections::class)->songs($slug);
            $this->assertCount($count, $songs, $slug);
            $intro = implode(' ', (array) __('site.collections.'.$slug.'.intro'));
            // Every song it lists is named, so a song joining or leaving changes the words first (the courses lead checked them).
            foreach ($songs as $song) {
                $this->assertStringContainsString($song['title'], $intro, $slug.': '.$song['title']);
            }
        }

        // Not folk songs, not barbershop or parlour: they stay in the A to Z, under Other songs.
        $listed = [...array_column(app(SongCollections::class)->songs('folk-songs'), 'slug'), ...array_column(app(SongCollections::class)->songs('barbershop-songs'), 'slug')];
        $index = collect(app(SongsHub::class)->index(app(Catalogue::class)->open()))->keyBy(static fn (array $e): string => basename($e['url']));
        foreach (['happy-birthday', 'now-is-the-month-of-maying', 'with-cat-like-tread'] as $slug) {
            $this->assertNotContains($slug, $listed);
            $this->assertSame(['other'], $index[$slug]['in'] ?? null, $slug);
        }
    }

    public function test_the_barbershop_intro_says_who_sings_the_tune_as_the_songs_are_voiced(): void
    {
        $tune = static fn (string $slug): array => array_values(array_column(array_filter(SongParts::parts(Template::query()->with('currentVersion')->where('slug', $slug)->firstOrFail()), static fn (array $p): bool => $p['melody']), 'key'));
        // "the lead has the tune" in Down by the Old Mill Stream, and in Asleep in the Deep "the bass sings the tune".
        $this->assertSame(['lead'], $tune('down-by-the-old-mill-stream'));
        $this->assertSame(['bass'], $tune('asleep-in-the-deep'));
        $intro = implode(' ', (array) __('site.collections.barbershop-songs.intro'));
        $this->assertStringContainsString('the lead has the tune', $intro);
        $this->assertStringContainsString('the bass sings the tune', $intro);
    }

    public function test_songs_counts_each_collection_as_its_page_lists_it(): void
    {
        $hub = app(SongsHub::class);
        $index = $hub->index(app(Catalogue::class)->open());
        foreach ($hub->bands() as $band) {
            if ($band['key'] === 'christmas') {
                continue;
            }
            $page = count(app(SongCollections::class)->songs($band['key']));
            // The panel's count, and the A to Z's filter for it, are the page's own.
            $this->assertSame($page, $band['count'], $band['key']);
            $this->assertSame($page, count(array_filter($index, static fn (array $e): bool => in_array($band['key'], $e['in'], true))), $band['key']);
        }
        $this->get('/songs')->assertSee('href="/songs/folk-songs"', false)->assertSee('href="/songs/barbershop-songs"', false)->assertSee(__('pages.songs.other'));
    }
}
