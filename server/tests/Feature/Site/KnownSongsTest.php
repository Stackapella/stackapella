<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Template;
use App\Models\User;
use App\Support\Tools\KnownSongs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The vocal key finder's known songs: the live library's reviewed songs with their key and melody's
 * range, never a withdrawn one, a person's own, or the notes.
 */
final class KnownSongsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_each_live_reviewed_song_gives_its_key_and_melody_range_and_nothing_more(): void
    {
        $this->catalogue();
        $songs = app(KnownSongs::class)->all();

        $grace = collect($songs)->firstWhere('slug', 'amazing-grace');
        $this->assertSame(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'key' => 'G', 'low' => 62, 'high' => 74, 'lowName' => 'D4', 'highName' => 'D5'], $grace);
        $this->assertStringNotContainsString('pitch', (string) json_encode($songs));
        $this->assertSame(collect($songs)->sortBy('title')->pluck('slug')->all(), collect($songs)->pluck('slug')->all());
        // Only the reviewed ones: an unreviewed song's notes may still change.
        $reviewed = Template::query()->listed()->whereHas('currentVersion', fn ($q) => $q->where('reviewed', true))->count();
        $this->assertGreaterThan(0, $reviewed);
        $this->assertCount($reviewed, $songs);
    }

    public function test_never_a_withdrawn_song_a_persons_own_or_an_unreviewed_one(): void
    {
        $this->catalogue();
        $all = count(app(KnownSongs::class)->all());

        // A song taken out of the library, and a person's own template made from one.
        Template::query()->where('slug', 'silent-night')->update(['withdrawn_at' => now()]);
        $mine = Template::query()->where('slug', 'amazing-grace')->firstOrFail()->replicate(['slug']);
        $mine->forceFill(['slug' => 'my-grace', 'owner_id' => User::factory()->create()->id])->save();
        Cache::forget(KnownSongs::CACHE);
        $slugs = array_column(app(KnownSongs::class)->all(), 'slug');

        $this->assertCount($all - (int) Template::query()->where('slug', 'silent-night')->whereHas('currentVersion', fn ($q) => $q->where('reviewed', true))->exists(), $slugs);
        $this->assertNotContains('silent-night', $slugs);
        $this->assertNotContains('my-grace', $slugs);

        // A song whose current version isn't reviewed yet.
        $this->assertContains('amazing-grace', $slugs);
        $this->catalogue(reviewed: false);
        Cache::forget(KnownSongs::CACHE);
        $this->assertNotContains('amazing-grace', array_column(app(KnownSongs::class)->all(), 'slug'));
    }

    public function test_a_sync_shows_its_songs_at_once(): void
    {
        Cache::put(KnownSongs::CACHE, [['slug' => 'stale']], 600);

        app(SyncOfficialTemplates::class)->handle();

        $this->assertFalse(Cache::has(KnownSongs::CACHE));
        $this->assertNotSame([['slug' => 'stale']], app(KnownSongs::class)->all());
    }
}
