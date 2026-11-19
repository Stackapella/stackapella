<?php

declare(strict_types=1);

namespace Tests\Feature\Videos;

use App\Enums\SongVideoHidden;
use App\Enums\SongVideoStatus;
use App\Models\Flag;
use App\Models\SongVideo;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * YouTube sync's server side (docs/youtube-sync-plan.md; Hillel, 2026-11-19): the admin's sync tool searches YouTube,
 * saves a recording's sync map once YouTube says the video may be shown and the map fits the song, and approves it;
 * singers see approved ones while youtube_sync is on for them; and nothing of YouTube's is kept past 30 days.
 */
final class SongVideosTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = 'abcdefghijk';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.key' => 'test-key']);
    }

    private function song(): Template
    {
        $song = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace']);
        TemplateVersion::factory()->create(['template_id' => $song->id, 'version' => 4]);

        return $song;
    }

    /**
     * YouTube's answer for one video: `status` and `contentDetails` overridable.
     *
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private static function item(string $id = self::VIDEO, array $status = [], string $rating = ''): array
    {
        return [
            'id' => $id,
            'snippet' => ['title' => 'Amazing Grace (choir)', 'channelTitle' => 'A choir'],
            'status' => ['embeddable' => true, 'privacyStatus' => 'public', 'madeForKids' => false, ...$status],
            'contentDetails' => ['duration' => 'PT3M34S', 'contentRating' => $rating === '' ? [] : ['ytRating' => $rating]],
        ];
    }

    /**
     * A fresh fake each time (a second Http::fake would add to the first, whose answers win).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $search
     */
    private static function youtube(array $items, array $search = []): void
    {
        Http::swap(new HttpFactory);
        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => $items]),
            'www.googleapis.com/youtube/v3/search*' => Http::response(['items' => $search]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private static function body(array $with = []): array
    {
        return [
            'song' => 'amazing-grace',
            'templateVersion' => 4,
            'videoId' => self::VIDEO,
            'form' => [
                ['kind' => 'section', 'section' => 0, 'shift' => -2, 'cents' => -8.04],
                ['kind' => 'gap', 'beats' => 6],
                ['kind' => 'section', 'section' => 0, 'shift' => -2, 'cents' => -12, 'words' => false, 'name' => 'Verse 2', 'extra' => 'dropped'],
            ],
            'anchors' => [[6.2, 0], [9.1, 3], [12.04567, 6]],
            'harmony' => 'similar',
            'otherWords' => [[54, 102]],
            ...$with,
        ];
    }

    public function test_the_admin_saves_a_map_that_fits_a_video_youtube_lets_us_show_and_approves_it(): void
    {
        $this->song();
        self::youtube([self::item()]);
        $this->actingAsAdmin(User::factory()->admin()->create(['name' => 'Hillel Coren']));

        $saved = $this->postJson('/api/admin/song-videos', self::body());
        $this->assertMatchesContract($saved, 'admin.songVideos.save', 201);
        $saved->assertJsonPath('data.status', 'draft')->assertJsonPath('data.title', 'Amazing Grace (choir)')
            ->assertJsonPath('data.durationSeconds', 214)->assertJsonPath('data.syncedBy.name', 'Hillel Coren')
            ->assertJsonPath('data.form.0', ['kind' => 'section', 'section' => 0, 'shift' => -2, 'cents' => -8])
            ->assertJsonPath('data.form.2', ['kind' => 'section', 'section' => 0, 'shift' => -2, 'cents' => -12, 'words' => false, 'name' => 'Verse 2'])
            ->assertJsonPath('data.anchors.2', [12.046, 6]);
        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'videos') && $r['key'] === 'test-key' && $r['id'] === self::VIDEO);

        $id = (string) $saved->json('data.id');
        $approved = $this->postJson("/api/admin/song-videos/{$id}/approve");
        $this->assertMatchesContract($approved, 'admin.songVideos.approve', 200);
        $approved->assertJsonPath('data.status', 'approved')->assertJsonPath('data.approvedBy.name', 'Hillel Coren');

        // Saving again replaces the map, and it needs approving again.
        $again = $this->postJson('/api/admin/song-videos', self::body(['harmony' => 'same']));
        $this->assertMatchesContract($again, 'admin.songVideos.save', 200);
        $again->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'draft')->assertJsonPath('data.approvedBy', null);
        $this->assertSame(1, SongVideo::query()->count());

        $list = $this->getJson('/api/admin/song-videos?song=amazing-grace');
        $this->assertMatchesContract($list, 'admin.songVideos.list', 200);
        $list->assertJsonCount(1, 'data');
        $this->assertMatchesContract($this->postJson('/api/admin/song-videos/nope1/approve'), 'admin.songVideos.approve', 404);

        $this->assertMatchesContract($this->deleteJson("/api/admin/song-videos/{$id}"), 'admin.songVideos.delete', 204);
        $this->assertMatchesContract($this->deleteJson("/api/admin/song-videos/{$id}"), 'admin.songVideos.delete', 404);
    }

    public function test_a_map_that_does_not_fit_or_a_video_we_may_not_show_is_refused_on_its_field(): void
    {
        $this->song();
        $this->actingAsAdmin(User::factory()->admin()->create());

        self::youtube([self::item()]);
        $this->postJson('/api/admin/song-videos', self::body(['form' => [['kind' => 'section', 'section' => 3]]]))->assertJsonValidationErrors(['form']);
        $this->postJson('/api/admin/song-videos', self::body(['anchors' => [[5, 0], [4, 3]]]))->assertJsonValidationErrors(['anchors']);
        $this->postJson('/api/admin/song-videos', self::body(['anchors' => [[5, 0], [9, 500]]]))->assertJsonValidationErrors(['anchors']);
        $this->postJson('/api/admin/song-videos', self::body(['form' => [['kind' => 'gap', 'beats' => 6]]]))->assertJsonValidationErrors(['form']);
        $this->postJson('/api/admin/song-videos', self::body(['videoId' => 'short']))->assertJsonValidationErrors(['videoId']);
        $this->assertMatchesContract($this->postJson('/api/admin/song-videos', self::body(['song' => 'no-such-song'])), 'admin.songVideos.save', 404);
        $this->assertMatchesContract($this->postJson('/api/admin/song-videos', self::body(['templateVersion' => 9])), 'admin.songVideos.save', 404);

        foreach ([
            [[], 'no such video'],
            [[self::item(status: ['embeddable' => false])], 'embedding off'],
            [[self::item(status: ['privacyStatus' => 'private'])], 'private'],
            [[self::item(status: ['madeForKids' => true])], 'made for kids'],
            [[self::item(rating: 'ytAgeRestricted')], 'age-restricted'],
        ] as [$items, $why]) {
            self::youtube($items);
            $refused = $this->postJson('/api/admin/song-videos', self::body());
            $this->assertMatchesContract($refused, 'admin.songVideos.save', 422);
            $refused->assertJsonValidationErrors(['videoId']);
        }
        $this->assertSame(0, SongVideo::query()->count());

        config(['services.youtube.key' => '']);
        $this->assertMatchesContract($this->postJson('/api/admin/song-videos', self::body()), 'admin.songVideos.save', 409);
    }

    public function test_the_admin_searches_youtube_and_nothing_is_kept(): void
    {
        self::youtube([self::item('aaaaaaaaaaa'), self::item('bbbbbbbbbbb', ['embeddable' => false])], [['id' => ['videoId' => 'aaaaaaaaaaa']], ['id' => ['videoId' => 'bbbbbbbbbbb']], ['id' => ['videoId' => 'ccccccccccc']]]);
        $this->actingAsAdmin(User::factory()->admin()->create());

        $found = $this->getJson('/api/admin/song-videos/search?q='.urlencode('silent night choir'));
        $this->assertMatchesContract($found, 'admin.songVideos.search', 200);
        $found->assertJsonCount(2, 'data')->assertJsonPath('data.0.usable', true)
            ->assertJsonPath('data.1.usable', false)->assertJsonPath('data.1.hiddenReason', 'not_embeddable');
        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'search') && $r['videoEmbeddable'] === 'true' && $r['type'] === 'video');
        $this->assertSame(0, SongVideo::query()->count());

        $this->assertMatchesContract($this->getJson('/api/admin/song-videos/search'), 'admin.songVideos.search', 422);
        config(['services.youtube.key' => '']);
        $this->assertMatchesContract($this->getJson('/api/admin/song-videos/search?q=x'), 'admin.songVideos.search', 409);
    }

    public function test_singers_see_approved_shown_recordings_only_while_the_switch_is_on(): void
    {
        $song = $this->song();
        $shown = SongVideo::factory()->approved()->create(['template_id' => $song->id, 'template_version' => 4]);
        SongVideo::factory()->create(['template_id' => $song->id]);
        SongVideo::factory()->approved()->create(['template_id' => $song->id, 'hidden_reason' => SongVideoHidden::NotEmbeddable]);

        $off = $this->getJson('/api/songs/amazing-grace/videos');
        $this->assertMatchesContract($off, 'songs.videos.list', 200);
        $off->assertJsonCount(0, 'data');

        Flag::query()->create(['name' => 'youtube_sync', 'value' => 'on']);
        $on = $this->getJson('/api/songs/amazing-grace/videos');
        $this->assertMatchesContract($on, 'songs.videos.list', 200);
        $on->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shown->sqid())->assertJsonPath('data.0.videoId', $shown->video_id);
        $this->assertMatchesContract($this->getJson('/api/songs/no-such-song/videos'), 'songs.videos.list', 404);
    }

    public function test_only_the_admin_uses_the_sync_tool(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/song-videos')->assertForbidden();
        $this->postJson('/api/admin/song-videos', self::body())->assertForbidden();
    }

    public function test_the_daily_check_refreshes_hides_and_deletes_what_youtube_no_longer_vouches_for(): void
    {
        $song = $this->song();
        $fresh = SongVideo::factory()->approved()->create(['template_id' => $song->id, 'checked_at' => now()->subDays(3)]);
        $due = SongVideo::factory()->approved()->create(['template_id' => $song->id, 'video_id' => 'ddddddddddd', 'title' => 'Old title', 'checked_at' => now()->subDays(26)]);
        $gone = SongVideo::factory()->approved()->create(['template_id' => $song->id, 'video_id' => 'eeeeeeeeeee', 'checked_at' => now()->subDays(26)]);
        $locked = SongVideo::factory()->approved()->create(['template_id' => $song->id, 'video_id' => 'fffffffffff', 'checked_at' => now()->subDays(27)]);
        $stale = SongVideo::factory()->create(['template_id' => $song->id, 'video_id' => 'ggggggggggg', 'checked_at' => now()->subDays(31)]);
        self::youtube([self::item('ddddddddddd'), self::item('fffffffffff', ['embeddable' => false])]);

        $this->artisan('app:videos:recheck')->assertSuccessful();

        $this->assertNotNull($fresh->fresh());
        $due->refresh();
        $this->assertSame(['Amazing Grace (choir)', null, true], [$due->title, $due->hidden_reason, $due->checked_at->isToday()]);
        $gone->refresh();
        $this->assertSame([SongVideoHidden::Removed, SongVideoStatus::Approved], [$gone->hidden_reason, $gone->status]);
        $this->assertSame(SongVideoHidden::NotEmbeddable, $locked->fresh()?->hidden_reason);
        $this->assertNull($stale->fresh(), 'a row unchecked for 30 days is gone');

        // Five days later the removed video's row, never refreshed, is gone too; the rest were checked.
        $this->travel(5)->days();
        $this->artisan('app:videos:recheck')->assertSuccessful();
        $this->assertNull($gone->fresh());
        $this->assertNotNull($due->fresh());

        // YouTube failing: nothing is refreshed, and the deletion still runs (a row at 29 days goes, before its 30th).
        $late = SongVideo::factory()->create(['template_id' => $song->id, 'video_id' => 'hhhhhhhhhhh', 'checked_at' => now()->subDays(29)->subHour()]);
        Http::swap(new HttpFactory);
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['code' => 403]], 403)]);
        $this->artisan('app:videos:recheck')->assertSuccessful();
        $this->assertNull($late->fresh());

        // No key: nothing is checked, and what can't be refreshed is still deleted on time.
        config(['services.youtube.key' => '']);
        $this->travel(31)->days();
        $this->artisan('app:videos:recheck')->assertSuccessful();
        $this->assertSame(0, SongVideo::query()->count());
    }
}
