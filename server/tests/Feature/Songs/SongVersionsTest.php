<?php

declare(strict_types=1);

namespace Tests\Feature\Songs;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Flags;
use App\Support\Templates\Catalogue;
use App\Support\Templates\SongVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Contract\AssertsContract;
use Tests\TestCase;

/**
 * A song's versions (Hillel, 2026-11-18, "One song, US/UK switch"): a UK version is its own song file whose `versionOf`
 * names the song. The song keeps one page and one place in every list, with a switch; UK visitors start on the UK version,
 * anyone may pick, and the version's own address leads to the song's page showing it.
 */
final class SongVersionsTest extends TestCase
{
    use AssertsContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true]);
    }

    /** @return array<string, string> a visitor through the edge, in a country */
    private static function visitor(string $country): array
    {
        return ['do-connecting-ip' => '172.70.1.2', 'cf-connecting-ip' => '198.51.100.7', 'cf-ipcountry' => $country];
    }

    /**
     * An open library song, its notes the factory's, with these makers (and, for a version, its link).
     *
     * @param  list<array<string, mixed>>  $makers
     * @param  array<string, string>|null  $versionOf
     */
    private function song(string $slug, array $makers = [], ?array $versionOf = null, string $tune = 'A tune', string $printed = 'US'): Template
    {
        $template = Template::factory()->create(['slug' => $slug, 'title' => 'It Came Upon the Midnight Clear', 'access' => TemplateAccess::Plus]);
        $data = (array) TemplateVersion::factory()->make()->data;
        $data = [...$data, 'id' => $slug, 'makers' => $makers, 'firstPublished' => ['year' => 1874, 'country' => $versionOf === null ? $printed : 'GB'],
            'credits' => [...(array) ($data['credits'] ?? []), 'tune' => $tune], ...($versionOf !== null ? ['versionOf' => $versionOf] : [])];
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        return $template->refresh();
    }

    /** It Came Upon the Midnight Clear, and its UK version ("Noel"). */
    private function carol(): void
    {
        $this->song('midnight-clear', [], null, '"Carol", Richard Storrs Willis');
        $this->song('midnight-clear-uk', [], ['song' => 'midnight-clear', 'name' => 'uk'], '"Noel", traditional air');
    }

    public function test_the_library_lists_the_song_once_with_its_versions_and_the_one_people_here_start_on(): void
    {
        $this->carol();

        foreach (['US' => 'midnight-clear', 'GB' => 'midnight-clear-uk', 'JE' => 'midnight-clear-uk', 'FR' => 'midnight-clear'] as $country => $here) {
            $response = $this->withHeaders(self::visitor($country))->getJson('/api/templates')->assertOk();
            $this->assertMatchesContract($response, 'templates.catalogue', 200);
            $songs = collect($response->json('data'));
            $this->assertSame(['midnight-clear'], $songs->pluck('id')->all(), $country);
            $this->assertSame([
                ['id' => 'midnight-clear', 'name' => 'us', 'here' => $here === 'midnight-clear'],
                ['id' => 'midnight-clear-uk', 'name' => 'uk', 'here' => $here === 'midnight-clear-uk'],
            ], $songs->first()['songVersions'], $country);
        }

        // The version itself is served by its own id, and never listed on its own (the site's lists neither).
        $version = $this->getJson('/api/templates/midnight-clear-uk')->assertOk();
        $this->assertEquals(['song' => 'midnight-clear', 'name' => 'uk'], $version->json('data.template.versionOf'));
        $this->assertSame(['midnight-clear'], app(Catalogue::class)->open()->pluck('slug')->all());
        $this->assertSame(['midnight-clear-uk'], app(Catalogue::class)->versions()->pluck('slug')->all());
    }

    public function test_a_song_without_versions_says_nothing_new(): void
    {
        $this->song('plain-song');

        $this->getJson('/api/templates')->assertOk()->assertJsonMissingPath('data.0.songVersions');
        $page = $this->withHeaders(self::visitor('GB'))->get('/songs/plain-song')->assertOk()->assertDontSee('data-song-versions', false);
        $this->assertSame([], $page->viewData('song')['versions']);
    }

    public function test_the_song_page_opens_on_the_version_sung_where_the_visitor_is_and_anyone_can_switch(): void
    {
        $this->carol();

        $us = $this->withHeaders(self::visitor('US'))->get('/songs/midnight-clear')->assertOk()->assertSee('data-song-versions', false);
        $this->assertSame('midnight-clear', $us->viewData('song')['slug']);
        $this->assertSame('midnight-clear', $us->viewData('line')['song']);
        $gb = $this->withHeaders(self::visitor('GB'))->get('/songs/midnight-clear')->assertOk();
        $this->assertSame('midnight-clear-uk', $gb->viewData('song')['slug']);
        $this->assertSame('midnight-clear-uk', $gb->viewData('line')['song']);
        $this->assertStringContainsString('song=midnight-clear-uk', (string) $gb->viewData('learn')['soprano']);
        // One address: the page's own, without a version, and the song's slug for the switch.
        $this->assertStringEndsWith('/songs/midnight-clear', (string) $gb->viewData('song')['pageUrl']);
        $gb->assertSee('<link rel="canonical" href="'.$gb->viewData('song')['pageUrl'].'">', false);
        $this->assertSame([['us', false], ['uk', true]], array_map(static fn (array $v): array => [$v['name'], $v['current']], $gb->viewData('song')['versions']));
        // A page that differs by country is never kept by a shared cache.
        $this->assertSame('no-store, private', $gb->headers->get('Cache-Control'));
        $this->assertNull($gb->headers->get('Cloudflare-CDN-Cache-Control'));

        // Asked for, a version shows wherever the visitor is.
        $picked = $this->withHeaders(self::visitor('GB'))->get('/songs/midnight-clear?version=us')->assertOk();
        $this->assertSame('midnight-clear', $picked->viewData('song')['slug']);
        $this->assertSame('midnight-clear-uk', $this->withHeaders(self::visitor('US'))->get('/songs/midnight-clear?version=uk')->viewData('song')['slug']);
        // A name it doesn't have is the visitor's own country's.
        $this->assertSame('midnight-clear', $this->withHeaders(self::visitor('US'))->get('/songs/midnight-clear?version=zz')->viewData('song')['slug']);
    }

    public function test_the_structured_data_names_both_versions(): void
    {
        $this->carol();

        $page = $this->withHeaders(self::visitor('GB'))->get('/songs/midnight-clear')->assertOk();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $page->getContent(), $match);
        $data = json_decode($match[1] ?? '[]', true);
        $composition = collect(is_array($data) && array_is_list($data) ? $data : [$data])->firstWhere('@type', 'MusicComposition');
        $arrangements = (array) ($composition['musicArrangement'] ?? []);
        $this->assertCount(2, $arrangements);
        $this->assertStringEndsWith('/songs/midnight-clear?version=us', (string) $arrangements[0]['url']);
        $this->assertStringEndsWith('/songs/midnight-clear?version=uk', (string) $arrangements[1]['url']);
        $this->assertStringContainsString('Noel', (string) $arrangements[1]['description']);
    }

    public function test_a_versions_own_address_leads_to_its_songs_page_showing_it(): void
    {
        $this->carol();

        $this->get('/songs/midnight-clear-uk')->assertStatus(301)->assertRedirect('/songs/midnight-clear?version=uk');
        $this->get('/songs/midnight-clear-uk/alto')->assertStatus(301)->assertRedirect('/songs/midnight-clear/alto?version=uk');
    }

    public function test_a_part_page_sings_the_version_and_keeps_it_on_its_links(): void
    {
        $this->carol();

        $gb = $this->withHeaders(self::visitor('GB'))->get('/songs/midnight-clear/alto')->assertOk()->assertSee('data-song-versions', false);
        $page = $gb->viewData('songPart');
        $this->assertSame('midnight-clear-uk', $page['song']['slug']);
        $this->assertSame('midnight-clear', $page['song']['pageSlug']);
        $this->assertStringEndsWith('/songs/midnight-clear/alto', (string) $page['canonical']);
        $this->assertStringEndsWith('/songs/midnight-clear?version=uk', (string) $page['song']['pageUrl']);
        $this->assertStringEndsWith('/songs/midnight-clear/soprano?version=uk', (string) collect($page['parts'])->firstWhere('key', 'soprano')['url']);
        $this->assertSame('no-store, private', $gb->headers->get('Cache-Control'));

        $us = $this->withHeaders(self::visitor('US'))->get('/songs/midnight-clear/alto?version=us')->assertOk();
        $this->assertSame('midnight-clear', $us->viewData('songPart')['song']['slug']);
    }

    public function test_where_the_song_is_withheld_but_its_version_is_free_the_version_stands_in(): void
    {
        // The song's arranger died in 1962 (Deck the Halls' Beattie): protected in life + 70 countries until 2033 (printed
        // in Britain here, so no shorter US term frees it in France); its UK version's in 1926, free there.
        $this->song('midnight-clear', [['name' => 'An Editor', 'role' => 'arrangement', 'died' => 1962, 'nationality' => 'GB']], null, 'A tune', 'GB');
        $this->song('midnight-clear-uk', [['name' => 'Charles Wood', 'role' => 'arrangement', 'died' => 1926, 'nationality' => 'GB']], ['song' => 'midnight-clear', 'name' => 'uk']);
        app(Flags::class)->set('geo_rights', 'on', null, 'test');

        // In France (life + 70) the song's own is withheld and the UK version is free: it stands in, alone.
        $fr = collect($this->withHeaders(self::visitor('FR'))->getJson('/api/templates')->assertOk()->json('data'));
        $this->assertSame(['midnight-clear-uk'], $fr->pluck('id')->all());
        $this->assertArrayNotHasKey('songVersions', $fr->first());
        // Its page opens on the version free there.
        $this->assertSame('midnight-clear-uk', $this->withHeaders(self::visitor('FR'))->get('/songs/midnight-clear')->assertOk()->viewData('song')['slug']);
        // In the US both are free (printed before 1931), and the US starts on the song's own.
        $us = collect($this->withHeaders(self::visitor('US'))->getJson('/api/templates')->json('data'));
        $this->assertSame(['midnight-clear'], $us->pluck('id')->all());
        $this->assertCount(2, $us->first()['songVersions']);
    }

    public function test_the_sync_refuses_a_version_of_a_song_that_isnt_one(): void
    {
        $folder = storage_path('framework/testing/versions-'.uniqid());
        File::ensureDirectoryExists($folder);
        config(['templates.folders' => [$folder]]);
        try {
            $song = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
            File::put($folder.'/amazing-grace.json', (string) json_encode($song));
            File::put($folder.'/amazing-grace-uk.json', (string) json_encode([...$song, 'id' => 'amazing-grace-uk', 'versionOf' => ['song' => 'amazing-grace', 'name' => 'uk']]));
            $this->artisan('app:templates:sync')->assertSuccessful();
            $this->assertSame(['amazing-grace' => [['name' => 'uk', 'slug' => 'amazing-grace-uk']]], app(SongVersions::class)->map());

            File::put($folder.'/other-uk.json', (string) json_encode([...$song, 'id' => 'other-uk', 'versionOf' => ['song' => 'no-such-song', 'name' => 'uk']]));
            $this->artisan('app:templates:sync', ['--strict' => true])->expectsOutputToContain("other-uk.json: versionOf names no-such-song, which isn't a song in its own right")->assertFailed();
        } finally {
            File::deleteDirectory($folder);
        }
    }
}
