<?php

declare(strict_types=1);

namespace Tests\Feature\Social;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Jobs\PostToSocial;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Models\SocialPost;
use App\Models\Template;
use App\Models\User;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * Our own posts on X and Bluesky (contract/api.md, "Books, posts and signing in with Event
 * Schedule (M10)"): proposed, approved by Hillel, sent after the undo window, once.
 */
final class SocialPostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.x' => ['api_key' => 'k', 'api_secret' => 's', 'access_token' => 't', 'access_secret' => 'a'],
            'services.bluesky' => ['handle' => 'stackapella.com', 'app_password' => 'pw', 'service' => 'https://bsky.social'],
            'services.social.live' => true,
        ]);

        Http::fake([
            'https://bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:stack']),
            'https://bsky.social/xrpc/com.atproto.repo.uploadBlob' => Http::response(['blob' => ['$type' => 'blob', 'ref' => ['$link' => 'b1']]]),
            'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:stack/app.bsky.feed.post/3kabc']),
            'https://api.x.com/2/media/upload' => Http::response(['data' => ['id' => 'm1']]),
            'https://api.x.com/2/tweets' => Http::sequence()->push(['data' => ['id' => '1001']])->push('', 503)->push(['data' => ['id' => '1002']]),
        ]);
    }

    /**
     * The official songs, with Amazing Grace and Silent Night as reviewed (as after the musician's check).
     */
    private function syncReviewed(): void
    {
        $folder = storage_path('framework/testing/templates-'.uniqid());
        mkdir($folder, 0777, true);

        foreach (['amazing-grace', 'silent-night'] as $slug) {
            $data = (array) json_decode((string) file_get_contents(is_file(base_path('../web/templates/'.$slug.'.json')) ? base_path('../web/templates/'.$slug.'.json') : database_path('templates/'.$slug.'.json')), true);
            file_put_contents($folder.'/'.$slug.'.json', (string) json_encode([...$data, 'reviewed' => true]));
        }

        config(['templates.folders' => [$folder]]);
        app(SyncOfficialTemplates::class)->handle();
    }

    private function live(): Harmony
    {
        // Its singer is an adult: featuring, like the gallery, is 18 and over.
        $harmony = Harmony::factory()->create(['project_id' => null, 'title' => 'Silent Night for Mum', 'user_id' => User::factory()->create(['adult_confirmed_at' => now()])->id]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    public function test_a_new_song_is_proposed_approved_by_hillel_and_sent_once_after_the_undo(): void
    {
        $this->syncReviewed();
        $grace = Template::query()->official()->where('slug', 'amazing-grace')->firstOrFail();
        $this->assertSame(2, SocialPost::query()->where('postable_id', $grace->id)->where('occasion', SocialPost::NEW_SONG)->count());
        app(SyncOfficialTemplates::class)->handle();
        $this->assertSame(2, SocialPost::query()->where('postable_id', $grace->id)->count());

        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/social');
        $this->assertMatchesContract($list, 'admin.social.list', 200);
        $list->assertJsonPath('networks', ['x', 'bluesky']);
        $this->assertMatchesContract($this->getJson('/api/admin/social?status=nope'), 'admin.social.list', 422);

        $sky = SocialPost::query()->where('postable_id', $grace->id)->where('network', 'bluesky')->firstOrFail();
        $x = SocialPost::query()->where('postable_id', $grace->id)->where('network', 'x')->firstOrFail();
        $approved = $this->postJson('/api/admin/social/'.$sky->sqid().'/approve');
        $this->assertMatchesContract($approved, 'admin.social.approve', 200);
        $this->assertMatchesContract($this->postJson('/api/admin/social/'.$sky->sqid().'/approve'), 'admin.social.approve', 409);
        $this->postJson('/api/admin/social/'.$x->sqid().'/approve')->assertOk();

        // Within the undo window nothing goes; after it, each goes once.
        $this->artisan('app:social:send')->assertSuccessful();
        $this->assertSame(SocialPost::QUEUED, $sky->refresh()->status);
        $this->travel(121)->seconds();
        $this->artisan('app:social:send')->assertSuccessful();
        $this->artisan('app:social:send')->assertSuccessful();

        $this->assertSame([SocialPost::POSTED, 'https://bsky.app/profile/stackapella.com/post/3kabc'], [$sky->refresh()->status, $sky->url]);
        $this->assertSame(SocialPost::POSTED, $x->refresh()->status);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'createRecord') && str_contains((string) $request['record']['text'], 'https://stackapella.test/?song=amazing-grace') && $request['record']['facets'] !== []);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/2/tweets') && ! str_contains((string) $request['text'], 'http') && str_contains((string) $request->header('Authorization')[0], 'oauth_signature='));
    }

    public function test_a_post_is_undone_turned_down_or_tried_again_and_nothing_goes_outside_production(): void
    {
        $grace = Template::factory()->create(['slug' => 'test-song', 'title' => 'Test Song']);
        $this->assertSame(0, SocialPost::query()->count());
        $this->syncReviewed();
        $song = Template::query()->official()->where('slug', 'amazing-grace')->firstOrFail();
        $this->actingAsAdmin();
        [$x, $sky] = [SocialPost::query()->where('postable_id', $song->id)->where('network', 'x')->firstOrFail(), SocialPost::query()->where('postable_id', $song->id)->where('network', 'bluesky')->firstOrFail()];

        // Undo within the window.
        $this->postJson('/api/admin/social/'.$sky->sqid().'/approve')->assertOk();
        $undone = $this->postJson('/api/admin/social/'.$sky->sqid().'/cancel');
        $this->assertTrue(AuditLog::query()->where('action', 'social.cancelled')->whereNotNull('actor_id')->exists());
        $this->assertMatchesContract($undone, 'admin.social.cancel', 200);
        $undone->assertJsonPath('data.status', 'cancelled');
        $this->assertMatchesContract($this->postJson('/api/admin/social/'.$sky->sqid().'/cancel'), 'admin.social.cancel', 409);

        // X fails once (the second answer in the fake is a 503), then goes on Retry.
        $this->postJson('/api/admin/social/'.$x->sqid().'/approve')->assertOk();
        $this->travel(121)->seconds();
        $this->artisan('app:social:send')->assertSuccessful();
        $first = SocialPost::query()->where('status', SocialPost::POSTED)->count();
        $this->assertMatchesContract($this->postJson('/api/admin/social/'.$x->sqid().'/retry'), 'admin.social.retry', $x->refresh()->status === SocialPost::FAILED ? 200 : 409);
        $this->assertGreaterThanOrEqual($first, SocialPost::query()->where('status', SocialPost::POSTED)->count());

        // Outside production an approved post is marked not sent.
        config(['services.social.live' => false]);
        $other = SocialPost::query()->where('status', SocialPost::AWAITING)->where('network', 'bluesky')->firstOrFail();
        $this->postJson('/api/admin/social/'.$other->sqid().'/approve')->assertOk();
        $this->travel(121)->seconds();
        $this->artisan('app:social:send')->assertSuccessful();
        $this->assertSame([SocialPost::CANCELLED, 'Not sent: posting is live only in production.'], [$other->refresh()->status, $other->error]);

        foreach (['admin.social.approve' => 'approve', 'admin.social.cancel' => 'cancel', 'admin.social.retry' => 'retry'] as $operation => $action) {
            $this->assertMatchesContract($this->postJson('/api/admin/social/nope/'.$action), $operation, 404);
        }

        $this->assertMatchesContract($this->postJson('/api/admin/social/'.$other->sqid().'/retry'), 'admin.social.retry', 409);
        $this->assertNotNull($grace);
    }

    public function test_someone_under_18_cant_say_yes_to_featuring(): void
    {
        $harmony = $this->live();
        $owner = $harmony->user;
        $owner->forceFill(['adult_confirmed_at' => null])->save();

        $this->actingAs($owner);
        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => true]), 'harmonies.update', 403);
        $this->assertNull($harmony->refresh()->may_feature_at);

        // A yes from before (or from an account that turned out younger) is never featured.
        $harmony->forceFill(['may_feature_at' => now()])->save();
        $this->actingAsAdmin();
        $this->assertMatchesContract($this->postJson('/api/admin/social/harmonies/'.$harmony->public_id), 'admin.social.feature', 409);
    }

    public function test_a_harmony_is_featured_only_with_its_singers_yes_and_a_no_withdraws_it(): void
    {
        $harmony = $this->live();
        $owner = $harmony->user;
        $owner->forceFill(['handle' => 'ana_sings', 'x_handle' => 'ana_on_x', 'bluesky_handle' => 'ana.bsky.social'])->save();
        $this->actingAsAdmin();
        $this->assertMatchesContract($this->postJson('/api/admin/social/harmonies/'.$harmony->public_id), 'admin.social.feature', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/social/harmonies/nope'), 'admin.social.feature', 404);

        $this->actingAs($owner);
        $yes = $this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => true]);
        $this->assertMatchesContract($yes, 'harmonies.update', 200);
        $yes->assertJsonPath('data.mayFeature', true);

        $this->actingAsAdmin(User::query()->where('is_admin', true)->firstOrFail());
        $proposed = $this->postJson('/api/admin/social/harmonies/'.$harmony->public_id);
        $this->assertMatchesContract($proposed, 'admin.social.feature', 201);
        $proposed->assertJsonCount(2, 'data')->assertJsonPath('data.0.about.kind', 'harmony');
        // Credited by their handle on each network, never their Stackapella one with an @.
        $this->assertStringContainsString('(@ana_on_x)', (string) $proposed->json('data.0.text'));
        $this->assertStringContainsString('(@ana.bsky.social)', (string) $proposed->json('data.1.text'));
        $this->assertStringNotContainsString('@ana_sings', (string) $proposed->json('data.0.text'));

        // The singer changes their mind: what hasn't gone, goes nowhere.
        $this->actingAs($owner);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => false])->assertOk()->assertJsonPath('data.mayFeature', false);
        $this->assertSame(2, SocialPost::query()->where('status', SocialPost::CANCELLED)->count());

        // Said yes again, then hidden by a moderator before Hillel approves: nothing to say.
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => true])->assertOk();
        SocialPost::query()->delete();
        $this->actingAsAdmin(User::query()->where('is_admin', true)->firstOrFail());
        $this->postJson('/api/admin/social/harmonies/'.$harmony->public_id)->assertCreated();
        $harmony->forceFill(['hidden_at' => now(), 'hidden_reason' => 'report'])->save();
        $post = SocialPost::query()->firstOrFail();
        $this->assertMatchesContract($this->postJson('/api/admin/social/'.$post->sqid().'/approve'), 'admin.social.approve', 409);
        $this->assertSame(SocialPost::CANCELLED, $post->refresh()->status);
    }

    public function test_a_featured_harmony_carries_its_own_share_video_once_videos_are_on(): void
    {
        Sleep::fake();
        Http::fake([
            'https://api.x.com/2/media/upload/initialize' => Http::response(['data' => ['id' => 'v1']]),
            'https://api.x.com/2/media/upload/v1/append' => Http::response([], 204),
            'https://api.x.com/2/media/upload/v1/finalize' => Http::response(['data' => ['id' => 'v1', 'processing_info' => ['state' => 'pending', 'check_after_secs' => 2]]]),
            'https://api.x.com/2/media/upload?*' => Http::response(['data' => ['id' => 'v1', 'processing_info' => ['state' => 'succeeded']]]),
        ]);
        config(['services.social.videos' => true]);
        $harmony = $this->live();
        $harmony->forceFill(['may_feature_at' => now()])->save();
        $video = ShareVideo::factory()->ready()->create(['harmony_id' => $harmony->id]);
        Storage::disk('private')->put((string) $video->file_key, 'mp4-bytes');

        $this->actingAsAdmin();
        $this->postJson('/api/admin/social/harmonies/'.$harmony->public_id)->assertCreated();

        foreach (SocialPost::query()->get() as $post) {
            $this->postJson('/api/admin/social/'.$post->sqid().'/approve')->assertOk();
        }

        $this->travel(121)->seconds();
        $this->artisan('app:social:send')->assertSuccessful();
        $this->assertSame(2, SocialPost::query()->where('status', SocialPost::POSTED)->count());

        // X: uploaded in chunks, waited for, then posted with it.
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/initialize') && $request['media_category'] === 'tweet_video' && $request['total_bytes'] === 9);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/2/tweets') && $request['media']['media_ids'] === ['v1']);
        Sleep::assertSlept(fn ($duration): bool => $duration->totalSeconds === 2.0, 1);
        // Bluesky: the video embedded, the link in the text.
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'createRecord') && $request['record']['embed']['$type'] === 'app.bsky.embed.video' && $request['record']['embed']['aspectRatio'] === ['width' => 1080, 'height' => 1920]);
    }

    public function test_a_video_that_fails_never_leaves_a_post_posting(): void
    {
        Sleep::fake();
        config(['services.social.videos' => true, 'services.bluesky.service' => 'https://pds.test']);
        Http::fake([
            'https://api.x.com/2/media/upload/initialize' => Http::response(['data' => ['id' => 'v2']]),
            'https://api.x.com/2/media/upload/v2/append' => Http::response([], 204),
            'https://api.x.com/2/media/upload/v2/finalize' => Http::response(['data' => ['id' => 'v2', 'processing_info' => ['state' => 'pending', 'check_after_secs' => 2]]]),
            'https://api.x.com/2/media/upload?*' => Http::response([], 503),
            'https://pds.test/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:stack']),
            'https://pds.test/xrpc/com.atproto.repo.uploadBlob' => Http::response([], 413),
            'https://pds.test/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:stack/app.bsky.feed.post/3kdef']),
        ]);
        $harmony = $this->live();
        $harmony->forceFill(['may_feature_at' => now()])->save();
        $video = ShareVideo::factory()->ready()->create(['harmony_id' => $harmony->id]);
        Storage::disk('private')->put((string) $video->file_key, 'mp4-bytes');

        $this->actingAsAdmin();
        $this->postJson('/api/admin/social/harmonies/'.$harmony->public_id)->assertCreated();

        foreach (SocialPost::query()->get() as $post) {
            $this->postJson('/api/admin/social/'.$post->sqid().'/approve')->assertOk();
        }

        $this->travel(121)->seconds();
        $this->artisan('app:social:send')->assertSuccessful();

        // X's status check failed: the post failed, never "succeeded" with media that isn't ready.
        $x = SocialPost::query()->where('network', 'x')->sole();
        $this->assertSame(SocialPost::FAILED, $x->status);
        $this->assertStringContainsString('ready', (string) $x->error);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/2/tweets'));
        // Bluesky refused the video: the card went instead.
        $this->assertSame(SocialPost::POSTED, SocialPost::query()->where('network', 'bluesky')->sole()->status);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'createRecord') && $request['record']['embed']['$type'] === 'app.bsky.embed.external');

        // On the long worker; one killed mid-post shows as failed.
        $this->assertSame('long', (new PostToSocial($x->id))->queue);
        $x->forceFill(['status' => SocialPost::POSTING])->save();
        (new PostToSocial($x->id))->failed(new RuntimeException('Timed out.'));
        $this->assertSame(SocialPost::FAILED, $x->refresh()->status);
    }

    public function test_a_sent_post_is_taken_down_when_its_singer_withdraws_or_deletes_it(): void
    {
        Http::fake([
            'https://bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt', 'did' => 'did:plc:stack']),
            'https://bsky.social/xrpc/com.atproto.repo.deleteRecord' => Http::response([]),
            'https://api.x.com/2/tweets/*' => Http::response(['data' => ['deleted' => true]]),
        ]);
        $harmony = $this->live();
        $harmony->forceFill(['may_feature_at' => now()])->save();
        $x = SocialPost::factory()->create(['postable_type' => $harmony->getMorphClass(), 'postable_id' => $harmony->id, 'occasion' => SocialPost::FEATURED, 'network' => 'x', 'status' => SocialPost::POSTED, 'remote_id' => '1001', 'url' => 'https://x.com/i/web/status/1001']);
        $sky = SocialPost::factory()->create(['postable_type' => $harmony->getMorphClass(), 'postable_id' => $harmony->id, 'occasion' => SocialPost::FEATURED, 'network' => 'bluesky', 'status' => SocialPost::POSTED, 'remote_id' => 'at://did:plc:stack/app.bsky.feed.post/3kabc']);

        $this->actingAs($harmony->user);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => false])->assertOk();

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->url() === 'https://api.x.com/2/tweets/1001');
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), 'deleteRecord') && $request['rkey'] === '3kabc');
        $this->assertSame([SocialPost::CANCELLED, SocialPost::CANCELLED], [$x->refresh()->status, $sky->refresh()->status]);
        $this->assertNull($x->url);
    }

    public function test_the_song_of_the_month_is_proposed_on_its_first_free_day(): void
    {
        $this->syncReviewed();
        SocialPost::query()->delete();
        Template::query()->official()->where('slug', 'silent-night')->update(['free_from' => now()->addDay()->toDateString(), 'free_until' => now()->addMonth()->toDateString()]);

        $this->artisan('app:social:month')->assertSuccessful();
        $this->assertSame(0, SocialPost::query()->count());

        $this->travel(1)->days();
        $this->artisan('app:social:month')->assertSuccessful();
        $this->artisan('app:social:month')->assertSuccessful();
        $this->assertSame(2, SocialPost::query()->where('occasion', SocialPost::MONTH.':'.now()->format('Y-m'))->count());
    }

    public function test_nothing_is_proposed_before_launch_and_waiting_posts_that_cant_go_are_cleared(): void
    {
        // Before launch: every song is new at launch, and nobody outside could open the link.
        config(['app.coming_soon' => true]);
        $this->syncReviewed();
        $this->assertSame(0, SocialPost::query()->count());

        // Waiting from before: cleared when the list is opened, with the reason.
        $early = SocialPost::factory()->create(['postable_type' => 'template', 'postable_id' => Template::query()->firstOrFail()->id, 'occasion' => SocialPost::NEW_SONG, 'network' => 'x', 'status' => SocialPost::AWAITING]);
        $this->actingAsAdmin();
        $this->getJson('/api/admin/social')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame([SocialPost::CANCELLED, 'Before launch: nothing is posted until the app is open to everyone.'], [$early->refresh()->status, $early->error]);

        // After launch: a post that waited over two weeks is stale; a fresh one waits.
        config(['app.coming_soon' => false]);
        $template = Template::query()->firstOrFail();
        $stale = SocialPost::factory()->create(['postable_type' => 'template', 'postable_id' => $template->id, 'occasion' => SocialPost::NEW_SONG, 'network' => 'bluesky', 'status' => SocialPost::AWAITING, 'created_at' => now()->subDays(15)]);
        $fresh = SocialPost::factory()->create(['postable_type' => 'template', 'postable_id' => $template->id, 'occasion' => SocialPost::MONTH.':'.now()->format('Y-m'), 'network' => 'x', 'status' => SocialPost::AWAITING]);
        $this->artisan('app:social:send')->assertSuccessful();
        $this->assertSame(SocialPost::CANCELLED, $stale->refresh()->status);
        $this->assertStringStartsWith('Stale', (string) $stale->error);
        // The month's post, for a song that isn't free this month: nothing to say.
        $this->assertSame([SocialPost::CANCELLED, 'Nothing to say any more: it changed or was taken down.'], [$fresh->refresh()->status, $fresh->error]);
    }
}
