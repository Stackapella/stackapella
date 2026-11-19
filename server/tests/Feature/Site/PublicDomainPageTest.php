<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use App\Support\Pages\PublicDomain;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BetaFixtures;
use Tests\TestCase;
use Tests\Unit\Pages\DivineNamesTest;

/**
 * The songs in the public domain, and the songs that will be (/songs/public-domain, App\Support\Pages\PublicDomain):
 * this coming 1 January first, every song on our site linked from its makers (in Spanish, to its Spanish page), a song
 * held somewhere saying where and until when, the works we don't have yet named but never linked, in the sitemap and
 * linked from /songs.
 */
final class PublicDomainPageTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
        Carbon::setTestNow('2026-11-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** The page's words without its tags, scripts or the other skin's labels; `$apart` keeps each element's words apart. */
    private static function text(string $html, bool $apart = false): string
    {
        $main = substr($html, (int) strpos($html, '<main'), (int) strpos($html, '</main>') - (int) strpos($html, '<main'));
        $main = (string) preg_replace('/<(script|style)\b.*?<\/\1>|<span class="s-skin-g"[^>]*>.*?<\/span>/s', ' ', $main);

        $main = $apart ? (string) preg_replace('/<[^>]+>/', ' | ', $main) : strip_tags($main);

        return html_entity_decode((string) preg_replace('/\s+/', ' ', $main), ENT_QUOTES);
    }

    public function test_it_opens_on_this_coming_first_of_january_and_links_every_song_on_our_site(): void
    {
        $response = $this->get(PublicDomain::PATH)->assertOk()
            ->assertSee('<title>'.e(__('site.public_domain.title')), false)
            ->assertSee('<link rel="canonical" href="'.e(Hosts::publicUrl().PublicDomain::PATH), false);
        $html = (string) $response->getContent();
        $text = self::text($html);

        // Public Domain Day 2027: Gerald Finzi died in 1956, so his songs are new to the public domain on 1 January 2027, each
        // song named first and its writer after it (Hillel: "I recognize the song names more than the authors").
        $this->assertStringContainsString('Public Domain Day 2027', $text);
        $this->assertStringContainsString('New to the public domain on 1 January 2027 in most of the world: songs by', $text);
        $this->assertMatchesRegularExpression('/Lo, the Full, Final Sacrifice.{0,400}?Gerald Finzi/s', $text);
        $this->assertStringContainsString('70 years after 1956', $text);

        // Every song on our site, by its makers or as one passed down without a name, links to its own page.
        foreach (app(Catalogue::class)->open() as $template) {
            $response->assertSee('href="/songs/'.$template->slug.'"', false);
        }
        // A work we don't have yet is named, never linked.
        $this->assertStringContainsString('Forest Green', $text);
        $this->assertDoesNotMatchRegularExpression('/<a [^>]*>Forest Green/', $html);

        // What people searching for public domain songs want first: our songs by title, then the year's songs and the questions.
        $response->assertSee('<span class="s-skin-s s-skin-sr">Public domain songs,</span>', false);
        foreach (['Public domain songs you can sing now', 'Songs entering the public domain in 2027, and in the years ahead', 'When does a song enter the public domain?', 'Are public domain songs free to use everywhere?'] as $heading) {
            $this->assertStringContainsString($heading, $text);
        }
        $this->assertLessThan(strpos($html, 'id="coming-h"'), strpos($html, 'id="songs-h"'));
        // The list shows its first songs until asked (the script's), but every song stays in the page for search.
        $count = app(Catalogue::class)->open()->count();
        $this->assertStringContainsString(trans_choice('site.public_domain.songs_all', $count, ['count' => $count]), $text);
        foreach (['"@type":"Dataset"', '"@type":"ItemList"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $response->assertSee($type, false);
        }
        $this->assertStringContainsString(__('site.public_domain.legal'), $text);
        // Hillel's design round (2026-11-19): a Year row jumps to each year, the dots have their legend, and there's no "Also on
        // this day"; and it celebrates the songs rather than mourning their writers: no "remembered" counts, no "years on".
        $this->assertStringNotContainsString('remembered', $text);
        $this->assertStringNotContainsString('years on', $text);
        $this->assertStringContainsString(__('site.public_domain.legend_now'), $text);
        $response->assertSee('href="#y2027" data-pd-jump="coming"', false)->assertSee('id="y2027"', false);
        $this->assertStringNotContainsString('Also on this day', $text);
        $this->assertStringNotContainsString('Still to come', $text);
        $this->assertStringNotContainsString("\u{2014}", $text);
    }

    public function test_a_song_held_somewhere_says_where_and_from_when_it_is_free_there(): void
    {
        $song = app(Catalogue::class)->open()->firstWhere('slug', 'lift-every-voice');
        $this->assertNotNull($song, 'Lift Every Voice and Sing is open');
        $held = array_values(array_filter(PublicDomain::held((array) $song->currentVersion?->data, 2026, PublicDomain::places()), static fn (array $h): bool => $h['year'] !== null));
        $this->assertNotSame([], $held, 'held somewhere (J. Rosamond Johnson died in 1954; Mexico counts a hundred years)');

        $text = self::text((string) $this->get(PublicDomain::PATH)->assertOk()->getContent());
        foreach ($held as $group) {
            $this->assertStringContainsString(__('site.public_domain.free_from', ['year' => $group['year'], 'places' => PublicDomain::listed($group['places'], $group['more'])]), $text);
        }
        $this->assertStringContainsString('Free from 2055: Mexico', $text);
    }

    public function test_a_co_maker_of_unknown_death_keeps_a_work_unfree_and_is_never_a_person_on_the_roll(): void
    {
        $html = (string) $this->get(PublicDomain::PATH)->assertOk()->getContent();
        $text = self::text($html);

        $this->assertStringContainsString('Debbie Friedman', $text);
        $this->assertStringContainsString(__('site.public_domain.waits_unknown', ['name' => 'Drorah Setel']), $text);
        $this->assertStringNotContainsString('id="drorah-setel', $html);
    }

    public function test_the_spanish_page_links_each_song_to_its_spanish_page(): void
    {
        $response = $this->get('/es'.PublicDomain::PATH)->assertOk()->assertSee('<html lang="es"', false);
        $text = self::text((string) $response->getContent());

        $this->assertStringContainsString('1 de enero de 2027', $text);
        $this->assertStringContainsString('Dominio público', $text);
        $this->assertStringContainsString('Canciones de dominio público que puedes cantar ya', $text);
        $response->assertSee('href="/es/songs/lift-every-voice"', false)->assertDontSee('href="/songs/lift-every-voice"', false);
    }

    public function test_it_is_in_the_sitemap_and_linked_from_the_songs_page(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(Hosts::publicUrl().PublicDomain::PATH, false);
        $this->get('/songs')->assertOk()->assertSee('href="'.PublicDomain::PATH.'"', false);
    }

    public function test_no_full_divine_name_is_written_on_the_roll(): void
    {
        foreach (['', '/es'] as $language) {
            $text = self::text((string) $this->get($language.PublicDomain::PATH)->assertOk()->getContent(), true);
            // Two Spanish and Catalan titles begin with the article "El", which isn't the Name.
            $text = str_replace(['El cant dels ocells', 'El derecho de vivir en paz'], '', $text);
            $this->assertSame([], DivineNamesTest::hits($text, $language === ''), $language.PublicDomain::PATH);
        }
    }
}
