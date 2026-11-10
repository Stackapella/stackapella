<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Support\Pages\SongParts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The song part pages (docs/seo-growth-plan.md, item 1): one page per part of each live, reviewed,
 * public-domain library song; a real 404 for anything else, and for every one until the page's view
 * exists; in the sitemap only then.
 */
final class SongPartPagesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private string $views;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    protected function tearDown(): void
    {
        if (isset($this->views)) {
            File::deleteDirectory($this->views);
        }
        parent::tearDown();
    }

    /** The payload as JSON, standing in front of marketing's page (site/song-part.blade.php) so these tests read it whole. */
    private function withPage(): void
    {
        $this->views = sys_get_temp_dir().'/stk-views-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->views.'/site');
        File::put($this->views.'/site/song-part.blade.php', '<pre id="payload">{{ json_encode($songPart) }}</pre>');
        View::getFinder()->prependLocation($this->views);
        View::getFinder()->flush();
    }

    /** @return array<string, mixed> */
    private function payload(string $path): array
    {
        $html = (string) $this->get($path)->assertOk()->getContent();
        preg_match('#<pre id="payload">(.*)</pre>#s', $html, $m);

        return (array) json_decode(html_entity_decode($m[1] ?? '{}', ENT_QUOTES), true);
    }

    public function test_the_part_pages_are_open_now_their_view_exists(): void
    {
        // Until marketing's view landed, every part page was a 404 and none was in the sitemap.
        $this->assertTrue(SongParts::open());
        $this->assertStringContainsString('/songs/amazing-grace/alto', (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_a_parts_page_gives_the_song_the_part_and_its_siblings_and_never_the_notes(): void
    {
        $this->withPage();
        $page = $this->payload('/songs/amazing-grace/alto');

        $this->assertStringEndsWith('/songs/amazing-grace/alto', $page['canonical']);
        $this->assertSame(['key' => 'alto', 'name' => 'Alto', 'melody' => false, 'low' => 59, 'high' => 71, 'lowName' => 'B3', 'highName' => 'B4'], $page['part']);
        $this->assertSame(['amazing-grace', 'Amazing Grace', 'G', 'soprano', true, true], [$page['song']['slug'], $page['song']['title'], $page['song']['key'], $page['song']['melodyPart'], $page['song']['free'], $page['song']['about'] === __('song_about.amazing-grace')]);
        $this->assertNotSame('', $page['song']['description']);
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column($page['parts'], 'key'));
        $this->assertSame([false, true, false, false], array_column($page['parts'], 'current'));
        $this->assertSame(['synth' => true, 'realVoice' => false, 'credit' => null, 'creditHandle' => null], $page['guide']);
        $this->assertStringContainsString('song=amazing-grace&part=alto&ref=part-page', $page['startUrl']);
        $this->assertStringNotContainsString('pitch', (string) json_encode($page));
        $this->assertStringNotContainsString('media', (string) json_encode($page));
    }

    public function test_a_real_voice_shows_and_dates_the_page(): void
    {
        $this->withPage();
        $grace = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        TemplateGuide::factory()->create(['template_version_id' => $grace->current_version_id, 'part_key' => 'alto', 'is_default' => true, 'published_at' => now()->addDay()]);

        $page = $this->payload('/songs/amazing-grace/alto');
        $this->assertTrue($page['guide']['realVoice']);
        $this->assertSame(now()->addDay()->utc()->toDateString(), $page['lastModified']);
        $this->assertFalse($this->payload('/songs/amazing-grace/tenor')['guide']['realVoice']);
    }

    public function test_only_live_reviewed_public_domain_songs_and_their_own_parts_have_pages(): void
    {
        $this->withPage();
        $this->get('/songs/amazing-grace/descant')->assertNotFound();
        $this->get('/songs/no-such-song/alto')->assertNotFound();
        // Lowercase only, on the public site (one origin gives the app's shell to an unknown address).
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://site.stackapella.test']);
        $this->get('https://site.stackapella.test/songs/amazing-grace/Alto')->assertNotFound();
        $this->get('https://site.stackapella.test/songs/amazing-grace/alto')->assertOk();
        config(['hosts.public_url' => null]);

        // Not reviewed.
        $this->catalogue(reviewed: false);
        $this->get('/songs/amazing-grace/alto')->assertNotFound();
        $this->catalogue();
        $this->get('/songs/amazing-grace/alto')->assertOk();

        // Words not public domain (as a song's file would say).
        $version = TemplateVersion::query()->findOrFail(Template::query()->where('slug', 'amazing-grace')->value('current_version_id'));
        $data = (array) $version->data;
        $original = $data['licence'];
        $data['licence'] = ['words' => 'used by permission', 'tune' => 'public domain'];
        DB::table('template_versions')->where('id', $version->id)->update(['data' => json_encode($data)]);
        $this->get('/songs/amazing-grace/alto')->assertNotFound();
        $data['licence'] = $original;
        DB::table('template_versions')->where('id', $version->id)->update(['data' => json_encode($data)]);

        // Withdrawn from the library.
        Template::query()->where('slug', 'amazing-grace')->update(['withdrawn_at' => now()]);
        $this->get('/songs/amazing-grace/alto')->assertNotFound();
    }

    public function test_the_song_pages_range_chart_links_each_part_to_its_page_once_there_are_pages(): void
    {
        $urls = fn (): array => array_column((array) $this->get('/songs/amazing-grace')->assertOk()->viewData('song')['ranges']['parts'], 'url');

        // The part pages exist now (marketing's site/song-part.blade.php), so the links are there from the start.
        $this->withPage();
        $linked = $urls();
        $this->assertCount(4, $linked);
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $i => $part) {
            $this->assertStringEndsWith('/songs/amazing-grace/'.$part, (string) $linked[$i]);
        }

        // The page draws one link a part (frontend's data-part-link hook).
        $html = (string) $this->get('/songs/amazing-grace')->getContent();
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $part) {
            $this->assertSame(1, substr_count($html, 'data-part-link="'.$part.'"'), $part);
        }

        // A song waiting for its Monday (not reviewed) has no part pages, and no song page either.
        $this->catalogue(reviewed: false);
        $this->get('/songs/amazing-grace')->assertNotFound();
        $this->assertNotContains('/songs/amazing-grace/alto', app(SongParts::class)->paths());
    }

    public function test_every_part_page_is_in_the_sitemap_with_its_date_and_counts_its_arrivals(): void
    {
        $this->withPage();
        $sitemap = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (app(SongParts::class)->paths() as $path) {
            $this->assertStringContainsString($path.'</loc>', $sitemap);
        }
        $this->assertContains('/songs/amazing-grace/bass', app(SongParts::class)->paths());
        $this->assertNotContains('/songs/'.Template::query()->listed()->whereHas('currentVersion', fn ($q) => $q->where('reviewed', false))->value('slug').'/soprano', app(SongParts::class)->paths());

        $this->postJson('/api/counts', ['events' => [['kind' => 'f_arrive']], 'signedIn' => false, 'source' => 'part-page'])->assertNoContent();
        $this->assertSame(1, (int) DB::table('usage_counts')->where('source', 'part-page')->sum('count'));
    }
}
