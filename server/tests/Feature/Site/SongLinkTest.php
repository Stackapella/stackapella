<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * A page's line to one open song in the library (site._song-link): the songwriters page links Daisy Bell while it's
 * open, and a song still waiting for its Monday is never linked.
 */
final class SongLinkTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_the_songwriters_page_links_its_song_while_its_open(): void
    {
        $slug = (string) __('site.for.pages.songwriters.song.slug');
        $this->get('/songs/'.$slug)->assertOk();

        $this->get('/for/songwriters')->assertOk()
            ->assertSee('href="/songs/'.$slug.'"', false)
            ->assertSee(e(__('site.for.pages.songwriters.song.line')), false);
    }

    public function test_a_song_that_isnt_open_is_never_linked(): void
    {
        $slug = (string) __('site.for.pages.songwriters.song.slug');
        $song = Template::query()->where('slug', $slug)->firstOrFail();
        DB::table('template_versions')->where('id', $song->current_version_id)->update(['reviewed' => false]);

        $this->get('/for/songwriters')->assertOk()->assertDontSee('href="/songs/'.$slug.'"', false);
    }
}
