<?php

declare(strict_types=1);

namespace Tests\Feature\Rights;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Flags;
use App\Support\Rights\RightsHere;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Contract\AssertsContract;
use Tests\TestCase;

/**
 * Songs by country (docs/geo-rights-plan.md, step 3), behind `geo_rights`: a song whose copyright still runs where the
 * visitor is answers 451 in the app and leaves the catalogue there; its page stays, without its player, and without
 * its words where they're the protected part; such a page is never kept by a shared cache. Off, nothing changes.
 */
final class GeoRightsTest extends TestCase
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
     * An open library song (Amazing Grace's notes) with these makers, printed in 1900 in the US.
     *
     * @param  list<array<string, mixed>>  $makers
     */
    private function song(string $slug, array $makers): Template
    {
        $template = Template::factory()->create(['slug' => $slug, 'title' => ucfirst($slug), 'access' => TemplateAccess::Free]);
        $data = [...(array) TemplateVersion::factory()->make()->data, 'makers' => $makers, 'firstPublished' => ['year' => 1900, 'country' => 'US']];
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        return $template->refresh();
    }

    /** Its words by someone who died in 1960: protected in Mexico (life + 100) to 2061, free in the US (printed 1900). */
    private function wordsSong(): Template
    {
        return $this->song('words-song', [['name' => 'A Composer', 'role' => 'music', 'died' => 1850], ['name' => 'A Poet', 'role' => 'words', 'died' => 1960]]);
    }

    private function on(): void
    {
        app(Flags::class)->set('geo_rights', 'on', null, 'test');
    }

    public function test_off_nothing_changes_anywhere(): void
    {
        $this->wordsSong();

        $this->withHeaders(self::visitor('MX'))->getJson('/api/templates/words-song')->assertOk();
        $this->withHeaders(self::visitor('MX'))->getJson('/api/templates')->assertJsonFragment(['id' => 'words-song']);
        $this->withHeaders(self::visitor('MX'))->getJson('/api/auth/config')->assertJsonPath('data.withheldSongs', []);
        $this->withHeaders(self::visitor('MX'))->getJson('/api/rights/here')->assertExactJson(['data' => ['country' => 'MX', 'withheld' => []]]);
        // The song and part pages keep exactly their shared cache, so the deploy can't slow them (the coordinator's ask).
        // As Symfony writes SongPagesController::headers()'s 'public, max-age=300'.
        $shared = 'max-age=300, public';
        foreach (['/songs/words-song', '/songs/words-song/soprano'] as $path) {
            foreach (['MX', 'US'] as $country) {
                $page = $this->withHeaders(self::visitor($country))->get($path)->assertOk();
                $this->assertSame($shared, $page->headers->get('Cache-Control'), $path.' '.$country);
            }
        }
        $song = $this->withHeaders(self::visitor('MX'))->get('/songs/words-song');
        $this->assertNull($song->viewData('rights'));
        $this->assertNotNull($song->viewData('line'));
        $this->assertNotSame([], $song->viewData('song')['words']);
    }

    public function test_on_a_song_protected_here_answers_451_and_leaves_the_catalogue_here_only(): void
    {
        $this->wordsSong();
        $this->on();

        $refused = $this->withHeaders(self::visitor('MX'))->getJson('/api/templates/words-song')
            ->assertStatus(451)->assertExactJson(['message' => 'Unavailable For Legal Reasons.', 'code' => 'not_available_here', 'freeFrom' => 2061]);
        $this->assertMatchesContract($refused, 'templates.show', 451);
        $this->withHeaders(self::visitor('US'))->getJson('/api/templates/words-song')->assertOk();

        $this->withHeaders(self::visitor('MX'))->getJson('/api/templates')->assertJsonMissing(['id' => 'words-song']);
        $this->withHeaders(self::visitor('US'))->getJson('/api/templates')->assertJsonFragment(['id' => 'words-song']);

        $this->withHeaders(self::visitor('MX'))->getJson('/api/auth/config')->assertJsonPath('data.withheldSongs', ['words-song']);
        $this->withHeaders(self::visitor('US'))->getJson('/api/auth/config')->assertJsonPath('data.withheldSongs', []);
        $here = $this->withHeaders(self::visitor('MX'))->getJson('/api/rights/here')->assertExactJson(['data' => ['country' => 'MX', 'withheld' => ['words-song']]]);
        $this->assertMatchesContract($here, 'rights.here', 200);
        // A visitor we can't place takes every country's rule.
        $this->flushHeaders();
        $this->getJson('/api/rights/here')->assertExactJson(['data' => ['country' => null, 'withheld' => ['words-song']]]);
    }

    public function test_an_admin_still_opens_and_lists_it_and_a_persons_own_song_is_never_withheld(): void
    {
        $this->wordsSong();
        $this->on();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->withHeaders(self::visitor('MX'))->getJson('/api/templates/words-song')->assertOk();
        $this->actingAs($admin)->withHeaders(self::visitor('MX'))->getJson('/api/templates')->assertJsonFragment(['id' => 'words-song']);

        $own = $this->song('own-song', [['name' => 'A Poet', 'role' => 'words', 'died' => 1960]])->forceFill(['owner_id' => $admin->id]);
        $this->assertNull(app(RightsHere::class)->withheld($own, 'MX'));
    }

    public function test_the_page_stays_without_its_player_and_its_words_and_no_shared_cache_keeps_it(): void
    {
        $this->wordsSong();
        $this->on();

        $mexico = $this->withHeaders(self::visitor('MX'))->get('/songs/words-song')->assertOk();
        $this->assertSame(['freeFrom' => 2061, 'words' => true], $mexico->viewData('rights'));
        $this->assertNull($mexico->viewData('line'));
        $this->assertSame([], $mexico->viewData('song')['words']);
        $this->assertStringContainsString('no-store', (string) $mexico->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $mexico->headers->get('Cache-Control'));

        // Free in the US, but it reads differently elsewhere: still private.
        $us = $this->withHeaders(self::visitor('US'))->get('/songs/words-song')->assertOk();
        $this->assertNull($us->viewData('rights'));
        $this->assertNotNull($us->viewData('line'));
        $this->assertNotSame([], $us->viewData('song')['words']);
        $this->assertStringContainsString('no-store', (string) $us->headers->get('Cache-Control'));
    }

    public function test_where_only_the_music_is_protected_the_words_stay(): void
    {
        // A tune by someone who died in 1960 on traditional words printed in 1900: the player goes in Mexico, not the words.
        $this->song('tune-song', [['name' => 'A Composer', 'role' => 'music', 'died' => 1960]]);
        $this->on();

        $page = $this->withHeaders(self::visitor('MX'))->get('/songs/tune-song')->assertOk();
        $this->assertSame(['freeFrom' => 2061, 'words' => false], $page->viewData('rights'));
        $this->assertNull($page->viewData('line'));
        $this->assertNotSame([], $page->viewData('song')['words']);
    }

    public function test_a_song_free_everywhere_keeps_its_shared_cache(): void
    {
        $this->song('old-song', [['name' => 'A Composer', 'role' => 'music', 'died' => 1850]]);
        $this->on();

        $page = $this->withHeaders(self::visitor('MX'))->get('/songs/old-song')->assertOk();
        $this->assertNull($page->viewData('rights'));
        $this->assertStringContainsString('public', (string) $page->headers->get('Cache-Control'));
    }
}
