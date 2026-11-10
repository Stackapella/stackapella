<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\Template;
use App\Support\Pages\SongCollections;
use App\Support\Pages\SongParts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        }
    }

    public function test_it_is_in_the_sitemap_and_linked_from_songs(): void
    {
        $this->assertStringContainsString('/songs/hymns<', (string) $this->get('/sitemap.xml')->getContent());
        $this->get('/songs')->assertOk()->assertSee('href="/songs/hymns"', false)->assertSee(__('site.collections.hymns.songs_link'));
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
