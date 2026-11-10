<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Pages\Carols;
use App\Support\Pages\SongParts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The Christmas carols page (/songs/christmas-carols, App\Support\Pages\Carols): each open carol with its parts' pages,
 * read from the library (a carol opens when its part pages do), so a hidden one is never named and an opened one joins
 * by itself; the weekly line only while it's true; in the sitemap, and on /songs in the carol season.
 */
final class CarolsPageTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_it_lists_each_open_carol_with_every_parts_page(): void
    {
        $carols = app(SongParts::class)->songs()->filter(static fn (Template $t): bool => Carols::isCarol($t));
        $this->assertNotEmpty($carols);

        $page = $this->get(Carols::PATH)->assertOk()->assertSee(__('site.carols.headline'));
        foreach ($carols as $carol) {
            $page->assertSee(e($carol->title), false);
            foreach (SongParts::parts($carol) as $part) {
                $page->assertSee('href="/songs/'.$carol->slug.'/'.$part['key'].'"', false);
            }
        }
        $page->assertSee('"@type":"ItemList"', false)->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('href="/for/choir-directors"', false);
    }

    public function test_a_hidden_carol_is_never_named_and_joins_by_itself_when_it_opens(): void
    {
        // A carol waiting for its Monday, made so here, so the test stands however many carols the library has open.
        $hidden = Template::query()->listed()->with('currentVersion')->get()
            ->first(static fn (Template $t): bool => Carols::isCarol($t) && SongParts::publicDomain((array) $t->currentVersion?->data));
        $this->assertInstanceOf(Template::class, $hidden, 'a public-domain carol in the library');
        DB::table('template_versions')->where('id', $hidden->current_version_id)->update(['reviewed' => false]);

        $this->get(Carols::PATH)->assertOk()->assertDontSee(e($hidden->title), false)->assertDontSee('/songs/'.$hidden->slug, false);

        DB::table('template_versions')->where('id', $hidden->current_version_id)->update(['reviewed' => true]);

        $this->get(Carols::PATH)->assertOk()->assertSee(e($hidden->title), false)->assertSee('href="/songs/'.$hidden->slug.'/', false);
    }

    public function test_each_carol_plays_its_opening_line_and_directors_start_a_group(): void
    {
        $carols = app(Carols::class)->songs();
        $html = (string) $this->get(Carols::PATH)->assertOk()
            ->assertSee(e(__('site.carols.choirs_body')), false)
            ->assertSee(e(Hosts::app('/groups?start=1&ref=carols')), false)
            ->assertSee('href="/for/choir-directors"', false)
            ->assertSee('/js/public/mixer.js', false)
            ->getContent();

        // One per card, and tonight's carol's in the countdown card.
        $this->assertSame(count($carols) + 1, substr_count($html, 'data-song>'));
        foreach ($carols as $carol) {
            $this->assertNotNull($carol['line'], $carol['slug']);
            $this->assertStringContainsString(e(__('site.carols.play', ['song' => $carol['title']])), $html);
        }
        // The steps for sending each part's link moved to the directors' page.
        $this->get('/for/choir-directors')->assertOk()->assertSee('Or send each section its part');
    }

    public function test_each_card_has_its_key_coverage_and_level_and_real_flat_and_sharp_signs(): void
    {
        $carols = collect(app(Carols::class)->songs())->keyBy('slug');
        $this->assertSame(['G major', 'Verse 1', 'Beginner'], $carols['away-in-a-manger']['meta']);
        $this->assertSame(['E♭ major', 'Verse 1', 'Beginner'], $carols['deck-the-halls']['meta']);
        $this->assertSame(['G major', 'Verse and refrain', 'Beginner'], $carols['hark-the-herald']['meta']);
        $this->assertSame(['G major', 'Verse and chorus', 'Beginner'], $carols['jingle-bells']['meta']);
        // Shchedryk's sections are named for who sings, so it says no coverage.
        $this->assertSame(['G minor', 'Intermediate'], $carols['shchedryk']['meta']);
        $this->assertSame('E♭5', $carols['deck-the-halls']['parts'][0]['highName']);
        $this->assertSame('F♯2', $carols['away-in-a-manger']['parts'][3]['lowName']);

        $this->get(Carols::PATH)->assertOk()->assertSee('E♭ major · Verse 1 · Beginner')->assertSee('E♭5');
    }

    public function test_the_landing_and_the_choir_guides_point_here_from_1_november_to_26_december(): void
    {
        $pages = ['/', '/guides/satb-explained', '/guides/practise-a-choir-part', '/guides/what-is-a-descant'];
        $link = 'href="'.Carols::PATH.'"';

        foreach (['2026-11-01 08:00:00', '2026-12-26 20:00:00', '2027-11-01 08:00:00'] as $day) {
            $this->travelTo($day);
            foreach ($pages as $page) {
                $this->get($page)->assertOk()->assertSee($link, false);
            }
        }
        $this->get('/')->assertSee(__('pages.landing.carols'));

        foreach (['2026-12-27 08:00:00', '2027-01-06 12:00:00', '2027-10-31 20:00:00'] as $day) {
            $this->travelTo($day);
            foreach ($pages as $page) {
                $this->get($page)->assertOk()->assertDontSee($link, false);
            }
        }
    }

    public function test_the_countdown_shows_from_november_to_christmas_day_and_never_after(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $carols = app(Carols::class)->songs();
        $page = $this->get(Carols::PATH)->assertOk()
            ->assertSee('data-countdown', false)
            ->assertSee('data-days>45<', false)
            ->assertSee(trans_choice('site.carols.days', 45))
            ->assertSee('<b>'.count($carols).'</b> '.trans_choice('site.carols.totals_carols', count($carols)), false)
            ->assertSee(trans_choice('site.carols.totals_each', 4, ['count' => 4]))
            ->assertSee(trans_choice('site.carols.count_line', 45, ['count' => 45]))
            ->assertSee(__('site.carols.tonight'));
        $tonight = Carols::tonight($carols);
        $this->assertNotNull($tonight);
        $page->assertSee('href="#carol-'.$tonight['slug'].'"', false);

        $this->travelTo('2026-12-24 20:00:00');
        $this->get(Carols::PATH)->assertOk()->assertSee('data-days>1<', false)->assertSee(trans_choice('site.carols.days', 1));

        $this->travelTo('2026-12-25 09:00:00');
        $this->get(Carols::PATH)->assertOk()->assertSee('data-countdown', false)->assertSee(__('site.carols.christmas'));

        foreach (['2026-12-26 08:00:00', '2027-01-10 12:00:00', '2027-10-31 12:00:00'] as $day) {
            $this->travelTo($day);
            $this->get(Carols::PATH)->assertOk()->assertDontSee('data-countdown', false)->assertDontSee(__('site.carols.tonight'));
        }
    }

    public function test_tonights_carol_is_the_same_for_everyone_on_a_day_and_moves_on_the_next(): void
    {
        $carols = app(Carols::class)->songs();
        $this->travelTo('2026-11-10 08:00:00');
        $morning = Carols::tonight($carols);
        $this->travelTo('2026-11-10 22:00:00');
        $this->assertSame($morning, Carols::tonight($carols));
        $this->travelTo('2026-11-11 08:00:00');
        $this->assertNotSame($morning, Carols::tonight($carols));
    }

    public function test_the_songbook_has_a_card_for_each_carol_and_an_i_sing_filter_for_each_part(): void
    {
        $carols = app(Carols::class)->songs();
        $html = (string) $this->get(Carols::PATH)->assertOk()->assertSee('/js/public/carols.js', false)->getContent();

        $this->assertSame(count($carols), substr_count($html, 'class="s-xcard '));
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $key) {
            $this->assertStringContainsString('data-sing="'.$key.'"', $html);
            $this->assertStringContainsString('<option value="'.$key.'">', $html);
        }
        $this->assertStringContainsString('data-sing="" aria-pressed="true"', $html);
    }

    public function test_the_seasons_look_shows_only_while_the_carols_are_pushed(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $this->get(Carols::PATH)->assertOk()->assertSee('class="s-xmas"', false)->assertSee(__('pages.nav.xmas'))->assertSee('s-xlights', false)->assertSee('class="s-xsnow" aria-hidden="true" data-snow', false);

        $this->travelTo('2027-02-01 12:00:00');
        $this->get(Carols::PATH)->assertOk()->assertDontSee('class="s-xmas"', false)->assertDontSee(__('pages.nav.xmas'))->assertDontSee('s-xlights', false)->assertDontSee('data-snow', false);
    }

    public function test_a_song_that_isnt_a_carol_stays_off(): void
    {
        // Amazing Grace is open (the fixture reviews it) and has part pages, but it's no carol.
        $this->get('/songs/amazing-grace/alto')->assertOk();
        $this->get(Carols::PATH)->assertOk()->assertDontSee('/songs/amazing-grace', false);
    }

    public function test_with_no_carol_open_the_page_is_gone_and_out_of_the_sitemap(): void
    {
        $this->assertStringContainsString(Carols::PATH.'<', (string) $this->get('/sitemap.xml')->getContent());

        $carols = Template::query()->listed()->with('currentVersion')->get()->filter(static fn (Template $t): bool => Carols::isCarol($t));
        DB::table('template_versions')->whereIn('id', $carols->pluck('current_version_id'))->update(['reviewed' => false]);

        $this->get(Carols::PATH)->assertNotFound();
        $this->assertStringNotContainsString(Carols::PATH, (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_a_carols_line_never_stops_at_a_mark_inside_its_own_title(): void
    {
        $hark = Template::query()->where('slug', 'hark-the-herald')->firstOrFail();
        DB::table('template_versions')->where('id', $hark->current_version_id)->update(['reviewed' => true]);

        $first = strtok((string) __('song_about.hark-the-herald'), "\n");
        $this->assertStringStartsWith('Hark! The Herald Angels Sing ', (string) $first);
        $hark = collect(app(Carols::class)->songs())->firstWhere('slug', 'hark-the-herald');
        $this->assertStringStartsWith('Hark! The Herald Angels Sing began', (string) $hark['from']);
    }

    public function test_the_weekly_line_shows_only_until_its_date(): void
    {
        config(['site.carols_weekly_until' => '2026-12-21']);

        $this->travelTo('2026-12-21 12:00:00');
        $this->get(Carols::PATH)->assertOk()->assertSee(__('site.carols.weekly'));

        $this->travelTo('2026-12-22 09:00:00');
        $this->get(Carols::PATH)->assertOk()->assertDontSee(__('site.carols.weekly'));
    }

    public function test_the_choir_pages_and_each_carols_part_pages_point_here_in_season_only(): void
    {
        $link = 'href="'.Carols::PATH.'"';
        $carol = app(Carols::class)->songs()[0];
        $pages = ['/guides/satb-explained', '/for/choir-singers', '/for/choir-directors', '/courses/learn-your-choir-part', $carol['parts'][0]['url']];

        $this->travelTo('2026-11-20 12:00:00');
        foreach ($pages as $path) {
            $this->get($path)->assertOk()->assertSee($link, false);
        }
        // A song that isn't a carol says nothing of carols.
        $this->get('/songs/amazing-grace/alto')->assertOk()->assertDontSee($link, false);

        $this->travelTo('2027-07-01 12:00:00');
        foreach ($pages as $path) {
            $this->get($path)->assertOk()->assertDontSee($link, false);
        }
    }

    public function test_songs_points_here_in_the_carol_season_only(): void
    {
        $link = 'href="'.Carols::PATH.'"';

        $this->travelTo('2026-11-09 12:00:00');
        $this->get('/songs')->assertOk()->assertSee($link, false);

        $this->travelTo('2027-01-06 12:00:00');
        $this->get('/songs')->assertOk()->assertSee($link, false);

        $this->travelTo('2027-07-01 12:00:00');
        $this->get('/songs')->assertOk()->assertDontSee($link, false);
    }
}
