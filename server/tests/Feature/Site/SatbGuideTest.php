<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\TemplateAccess;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The SATB guide (docs/search-content-plan.md, item 2): the question answered in its title and first sentence, each
 * part's usual range to hear (site._guide-ranges), its song's part pages while they're open, and its questions with
 * FAQPage data. The vocal percussion guide is listed with the others; an SATB song's pages say SATB; a guide plays its song whenever the song is open.
 */
final class SatbGuideTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_it_answers_the_question_plays_each_range_and_links_each_part(): void
    {
        $page = $this->get('/guides/satb-explained')->assertOk()
            ->assertSee('<title>What is SATB? Soprano, alto, tenor and bass explained', false)
            ->assertSee('<strong>SATB</strong> stands for soprano, alto, tenor and bass', false)
            ->assertSee('/js/public/guide-ranges.js', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee(e(__('pages.guides.faqs.satb-explained.0.0')), false);

        $this->assertSame(4, substr_count((string) $page->getContent(), 'data-range-play'));
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $part) {
            $page->assertSee('href="/songs/amazing-grace/'.$part.'"', false)->assertSee('href="/voice-types/'.$part.'"', false);
        }
    }

    public function test_its_songs_part_pages_are_linked_only_while_open(): void
    {
        $song = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        DB::table('template_versions')->where('id', $song->current_version_id)->update(['reviewed' => false]);

        $this->get('/guides/satb-explained')->assertOk()->assertDontSee('href="/songs/amazing-grace/', false);
    }

    public function test_an_satb_songs_pages_say_satb(): void
    {
        $this->get('/songs/amazing-grace')->assertOk()
            ->assertSee('<meta name="description" content="'.e(__('pages.song.description_satb', ['title' => 'Amazing Grace'])).'"', false);
        $this->get('/songs/amazing-grace/alto')->assertOk()->assertSee('of Amazing Grace (SATB) by ear', false);
    }

    public function test_a_guide_plays_its_song_whenever_it_is_open_and_never_when_hidden(): void
    {
        // O Come, All Ye Faithful isn't bundled with the app, but it's open, so its guide plays its opening line.
        $song = Template::query()->where('slug', 'o-come-all-ye-faithful')->with('currentVersion')->firstOrFail();
        $this->assertNotSame(TemplateAccess::Free, $song->access);
        $this->get('/guides/what-is-a-descant')->assertOk()->assertSee('id="guide-mixer"', false)->assertSee('/js/public/mixer.js', false);

        DB::table('template_versions')->where('id', $song->current_version_id)->update(['reviewed' => false]);
        $this->get('/guides/what-is-a-descant')->assertOk()->assertDontSee('id="guide-mixer"', false)->assertDontSee('/js/public/mixer.js', false);
    }

    public function test_a_guide_without_the_panel_or_questions_has_neither(): void
    {
        $this->get('/guides/vocal-percussion')->assertOk()
            ->assertDontSee('data-range-play', false)->assertDontSee('"@type":"FAQPage"', false);
        $this->get('/guides')->assertOk()->assertSee('href="/guides/vocal-percussion"', false);
    }
}
