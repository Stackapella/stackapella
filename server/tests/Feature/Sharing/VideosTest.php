<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Jobs\RenderShareVideo;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

final class VideosTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    public function test_a_30_second_harmony_renders_to_a_vertical_mp4_of_the_section_plus_the_end_card(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->owner();
        [$project] = $this->recordedProject(30.0);
        $harmony = $this->shareHarmony($project, Wav::stereo(30.0));
        $this->assertNotNull($harmony->template_version_id);
        $this->assertSame('soprano', $harmony->words_part_key);

        $made = $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', ['startSeconds' => 0, 'endSeconds' => 30, 'look' => 'dark', 'words' => true]);
        $this->assertMatchesContract($made, 'videos.create', 202);

        $shown = $this->getJson('/api/videos/'.$made->json('data.id'));
        $this->assertMatchesContract($shown, 'videos.show', 200);
        $shown->assertJsonPath('data.status', 'ready')->assertJsonPath('data.progress', 1);
        $this->assertNotNull($shown->json('data.url'));

        $video = ShareVideo::query()->firstOrFail();
        $file = (string) tempnam(sys_get_temp_dir(), 'video-test');
        file_put_contents($file, (string) Storage::disk('private')->get((string) $video->file_key));

        $probe = json_decode(Process::run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_name,width,height:format=duration', '-of', 'json', $file])->output(), true);
        @unlink($file);
        $streams = collect((array) ($probe['streams'] ?? []));
        $this->assertSame(['h264', 1080, 1920], [$streams->firstWhere('codec_name', 'h264')['codec_name'] ?? null, $streams->firstWhere('codec_name', 'h264')['width'] ?? null, $streams->firstWhere('codec_name', 'h264')['height'] ?? null]);
        $this->assertNotNull($streams->firstWhere('codec_name', 'aac'));
        // The section, then the 2-second end card.
        $this->assertEqualsWithDelta(32.0, (float) ($probe['format']['duration'] ?? 0), 0.15);
        $this->assertLessThan(15_000_000, $video->bytes);
        $this->assertTrue($video->expires_at?->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
    }

    public function test_the_same_choices_again_answer_the_same_video_without_counting_and_the_limit_refuses_more(): void
    {
        Queue::fake();
        $this->owner();
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => auth()->id(), 'frames' => 48000 * 30]);
        $choices = ['startSeconds' => 0, 'endSeconds' => 20, 'look' => 'dark', 'words' => true];

        $first = $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', $choices)->assertStatus(202);
        $again = $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', $choices);
        $this->assertMatchesContract($again, 'videos.create', 200);
        $again->assertJsonPath('data.id', $first->json('data.id'));
        Queue::assertPushed(RenderShareVideo::class, 1);

        // 3 an hour: this was one, two more, then refused.
        $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', [...$choices, 'look' => 'light'])->assertStatus(202);
        $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', [...$choices, 'words' => false])->assertStatus(202);
        $limited = $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', [...$choices, 'endSeconds' => 25]);
        $this->assertMatchesContract($limited, 'videos.create', 429);
    }

    public function test_a_video_needs_an_unlisted_ready_harmony_and_a_section_of_3_to_60_seconds_inside_it(): void
    {
        Queue::fake();
        $user = $this->owner();
        $private = Harmony::factory()->private()->create(['project_id' => null, 'user_id' => $user->id]);
        $uploading = Harmony::factory()->uploading()->create(['project_id' => null, 'user_id' => $user->id]);
        $hidden = Harmony::factory()->underReview()->create(['project_id' => null, 'user_id' => $user->id]);
        $live = Harmony::factory()->create(['project_id' => null, 'user_id' => $user->id]);
        $choices = ['startSeconds' => 0, 'endSeconds' => 10];

        $this->assertMatchesContract($this->postJson('/api/harmonies/'.$private->public_id.'/videos', $choices), 'videos.create', 409);
        $this->postJson('/api/harmonies/'.$private->public_id.'/videos', $choices)->assertJsonPath('code', 'harmony_private');
        $this->postJson('/api/harmonies/'.$uploading->public_id.'/videos', $choices)->assertJsonPath('code', 'harmony_not_ready');
        $this->postJson('/api/harmonies/'.$hidden->public_id.'/videos', $choices)->assertJsonPath('code', 'harmony_unavailable');
        $this->assertMatchesContract($this->postJson('/api/harmonies/'.Harmony::factory()->create()->public_id.'/videos', $choices), 'videos.create', 404);

        // The factory's harmony is 30 seconds long.
        $short = $this->postJson('/api/harmonies/'.$live->public_id.'/videos', ['startSeconds' => 5, 'endSeconds' => 6]);
        $this->assertMatchesContract($short, 'videos.create', 422);
        $this->postJson('/api/harmonies/'.$live->public_id.'/videos', ['startSeconds' => 20, 'endSeconds' => 40])->assertJsonValidationErrors('endSeconds');
        $this->postJson('/api/harmonies/'.$live->public_id.'/videos', ['startSeconds' => 0, 'endSeconds' => 10, 'look' => 'neon'])->assertJsonValidationErrors('look');
        $this->assertSame(0, ShareVideo::query()->count());

        $this->assertMatchesContract($this->getJson('/api/videos/'.ShareVideo::factory()->create()->sqid()), 'videos.show', 404);
    }

    public function test_renders_wait_for_a_free_slot_and_while_videos_are_paused_then_time_out_after_the_hour(): void
    {
        $video = ShareVideo::factory()->create();
        $job = fn (): RenderShareVideo => (new RenderShareVideo($video->id))->withFakeQueueInteractions();

        // Both slots taken by renders elsewhere: it waits.
        $held = [Cache::lock('share-video-slot:0', 60), Cache::lock('share-video-slot:1', 60)];
        foreach ($held as $lock) {
            $lock->get();
        }
        $waiting = $job();
        app()->call([$waiting, 'handle']);
        $waiting->assertReleased(10);
        $this->assertSame(ShareVideo::QUEUED, $video->fresh()?->status);
        foreach ($held as $lock) {
            $lock->release();
        }

        app(Flags::class)->set('pause_videos', 'on', null, 'The queue is full');
        $paused = $job();
        app()->call([$paused, 'handle']);
        $paused->assertReleased(30);

        $this->travel(61)->minutes();
        app()->call([$job(), 'handle']);
        $this->assertSame(['failed', 'timed_out'], [$video->fresh()?->status, $video->fresh()?->failure]);
    }

    public function test_asking_again_after_a_video_expires_makes_a_new_one_and_a_hidden_harmonys_never_renders(): void
    {
        Queue::fake();
        $user = $this->owner();
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $user->id]);
        $old = ShareVideo::factory()->ready()->create(['harmony_id' => $harmony->id, 'start_ms' => 0, 'end_ms' => 10000, 'expires_at' => now()->subMinute()]);

        $again = $this->postJson('/api/harmonies/'.$harmony->public_id.'/videos', ['startSeconds' => 0, 'endSeconds' => 10, 'look' => 'dark', 'words' => true])->assertStatus(202);
        $this->assertNotSame($old->sqid(), $again->json('data.id'));

        $queued = ShareVideo::query()->latest('id')->firstOrFail();
        $harmony->forceFill(['visibility' => Harmony::PRIVATE])->save();
        app()->call([new RenderShareVideo($queued->id), 'handle']);
        $this->assertSame(['failed', 'render_error'], [$queued->fresh()?->status, $queued->fresh()?->failure]);
    }

    public function test_an_expired_video_is_shown_as_expired_and_the_hourly_prune_deletes_its_file(): void
    {
        $user = $this->owner();
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $user->id]);
        $video = ShareVideo::factory()->ready()->create(['harmony_id' => $harmony->id, 'expires_at' => now()->subMinute()]);
        Storage::disk('private')->put((string) $video->file_key, 'mp4');
        $stale = Harmony::factory()->uploading()->create(['project_id' => null, 'user_id' => $user->id, 'created_at' => now()->subDays(2)]);
        Storage::disk('private')->put($stale->upload_key, 'wav');

        $this->getJson('/api/videos/'.$video->sqid())->assertJsonPath('data.status', 'expired')->assertJsonPath('data.url', null);

        $this->artisan('app:sharing:prune')->assertSuccessful();

        Storage::disk('private')->assertMissing((string) $video->file_key);
        Storage::disk('private')->assertMissing($stale->upload_key);
        $this->assertSame(ShareVideo::EXPIRED, $video->fresh()?->status);
        $this->assertNull($stale->fresh());
    }
}
