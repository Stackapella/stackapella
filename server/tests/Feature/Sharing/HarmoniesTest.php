<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Jobs\ProcessHarmony;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Models\Take;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFacts;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

final class HarmoniesTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    public function test_a_mix_goes_up_like_a_take_and_becomes_a_live_page_with_its_aac_waveform_and_card(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $wav = Wav::stereo(2.0);

        $created = $this->startHarmony($project, $wav);
        $this->assertMatchesContract($created, 'harmonies.create', 201);
        $created->assertJsonPath('data.status', 'uploading')->assertJsonPath('data.channels', 2)->assertJsonPath('upload.method', 'PUT');
        $id = (string) $created->json('data.id');
        $harmony = Harmony::query()->where('public_id', $id)->firstOrFail();

        $incomplete = $this->postJson('/api/harmonies/'.$id.'/complete');
        $this->assertMatchesContract($incomplete, 'harmonies.complete', 409);
        Storage::disk('private')->put($harmony->upload_key, Wav::stereo(2.0, hz: 330));
        $mismatch = $this->postJson('/api/harmonies/'.$id.'/complete');
        $this->assertMatchesContract($mismatch, 'harmonies.complete', 409);
        $mismatch->assertJsonPath('code', 'upload_mismatch');

        Storage::disk('private')->put($harmony->upload_key, $wav);
        $this->assertMatchesContract($this->postJson('/api/harmonies/'.$id.'/complete'), 'harmonies.complete', 200);

        $shown = $this->getJson('/api/harmonies/'.$id);
        $this->assertMatchesContract($shown, 'harmonies.show', 200);
        $shown->assertJsonPath('data.status', 'ready')->assertJsonPath('data.moderation', null)->assertJsonPath('data.projectId', $project);
        $this->assertNotNull($shown->json('data.audioUrl'));
        $this->assertSame(route('harmony.page', ['harmony' => $id]), $shown->json('data.pageUrl'));

        $harmony->refresh();
        Storage::disk('private')->assertMissing($harmony->upload_key);
        $this->assertCount(ProcessHarmony::PEAKS, (array) $harmony->peaks);
        // The page is live: the AAC and the card are on the public bucket too.
        Storage::disk('media')->assertExists((string) $harmony->media_audio_key);
        Storage::disk('media')->assertExists((string) $harmony->media_card_key);
        $card = (string) Storage::disk('media')->get((string) $harmony->media_card_key);
        $size = getimagesizefromstring($card);
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], [$size[0] ?? 0, $size[1] ?? 0, $size[2] ?? 0]);
        $this->assertLessThan(300_000, strlen($card));
        $this->assertSame('ftyp', substr((string) Storage::disk('private')->get((string) $harmony->audio_key), 4, 4));

        $list = $this->getJson('/api/harmonies?projectId='.$project);
        $this->assertMatchesContract($list, 'harmonies.list', 200);
        $list->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/harmonies?projectId='.Str::uuid())->assertJsonCount(0, 'data');
    }

    public function test_an_outbox_retry_answers_the_same_harmony_and_other_numbers_under_its_id_are_refused(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $wav = Wav::stereo(1.0);
        $change = (string) Str::uuid();

        $first = $this->startHarmony($project, $wav, ['changeId' => $change])->assertCreated();
        $this->startHarmony($project, $wav, ['changeId' => $change])->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->startHarmony($project, Wav::stereo(1.5), ['changeId' => $change])->assertStatus(422)->assertJsonValidationErrors('changeId');
        $this->assertSame(1, Harmony::query()->count());
    }

    public function test_the_mix_check_refuses_a_mix_that_couldnt_have_come_from_the_project(): void
    {
        $this->owner();
        [$empty, $part] = $this->projectWithPart();
        $wav = Wav::stereo(1.0);

        $missing = $this->startHarmony((string) Str::uuid(), $wav);
        $this->assertMatchesContract($missing, 'harmonies.create', 409);
        $missing->assertJsonPath('code', 'project_missing');

        $none = $this->startHarmony($empty, $wav);
        $this->assertMatchesContract($none, 'harmonies.create', 409);
        $none->assertJsonPath('code', 'no_takes');

        // A 2-second take: a mix of 20 seconds is more than its length and the reverb's tail.
        [$recorded] = $this->recordedProject(2.0);
        $long = $this->startHarmony($recorded, Wav::stereo(20.0));
        $this->assertMatchesContract($long, 'harmonies.create', 409);
        $long->assertJsonPath('code', 'mix_mismatch');
        $this->startHarmony($recorded, Wav::stereo(16.0))->assertCreated();

        $this->startHarmony($recorded, $wav, ['partId' => $part])->assertStatus(409)->assertJsonPath('code', 'part_missing');

        $this->deleteJson('/api/projects/'.$recorded.'?version=1&changeId='.Str::uuid())->assertNoContent();
        $binned = $this->startHarmony($recorded, $wav);
        $this->assertMatchesContract($binned, 'harmonies.create', 409);
        $binned->assertJsonPath('code', 'binned');
    }

    public function test_the_numbers_must_describe_the_wav_and_titles_may_not_carry_links(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $wav = Wav::stereo(1.0);

        $bad = $this->startHarmony($project, $wav, ['title' => 'Listen at mysite.com', 'bytes' => strlen($wav) + 2, 'sampleRate' => 96000, 'visibility' => 'public']);
        $this->assertMatchesContract($bad, 'harmonies.create', 422);
        $bad->assertJsonValidationErrors(['title', 'bytes', 'sampleRate', 'visibility']);
        $this->assertSame(__('validation.no_links'), $bad->json('errors.title.0'));

        $this->startHarmony($project, $wav, ['tracks' => [['name' => 'Alto', 'color' => '#E4A94B', 'peaks' => 'not base64!']]])->assertJsonValidationErrors(['tracks.0.color', 'tracks.0.peaks']);
        $this->startHarmony($project, $wav, ['tracks' => [['name' => 'Alto', 'color' => '#e4a94b', 'peaks' => base64_encode(str_repeat('x', 200))]]])->assertJsonValidationErrors('tracks.0.peaks');
    }

    public function test_a_title_on_the_word_lists_is_held_and_shows_as_untitled_until_hillel_looks(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();

        $harmony = $this->shareHarmony($project, with: ['title' => 'Free money for you']);

        $this->assertNotNull($harmony->title_held_at);
        $this->getJson('/api/harmonies/'.$harmony->public_id)->assertJsonPath('data.titleHeld', true)->assertJsonPath('data.title', 'Free money for you');

        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['title' => 'For Gran'])->assertOk()->assertJsonPath('data.titleHeld', false);
    }

    public function test_just_my_part_shares_one_part_and_keeps_its_name(): void
    {
        $this->owner();
        [$project, $part] = $this->recordedProject();

        $harmony = $this->shareHarmony($project, Wav::tone(1.0), ['partId' => $part, 'tracks' => [['name' => 'Alto', 'color' => '#e4a94b', 'peaks' => base64_encode(str_repeat(chr(90), 20))]]]);

        $this->getJson('/api/harmonies/'.$harmony->public_id)->assertJsonPath('data.partId', $part)->assertJsonPath('data.channels', 1)->assertJsonPath('data.status', 'ready');
        $this->assertSame('Alto', $harmony->part_name);
    }

    public function test_sharing_is_counted_by_trust_level_paused_by_the_switch_and_never_for_an_unverified_account(): void
    {
        $user = $this->owner();
        [$project] = $this->recordedProject();
        $wav = Wav::stereo(1.0);

        // Level 1 (verified, new): 3 a day. A private harmony counts toward nothing.
        $this->assertSame(TrustLevel::VERIFIED, app(TrustLevel::class)->of($user));
        $this->startHarmony($project, $wav, ['visibility' => 'private'])->assertCreated();
        foreach (range(1, 3) as $i) {
            $this->startHarmony($project, $wav)->assertCreated();
        }
        $limited = $this->startHarmony($project, $wav);
        $this->assertMatchesContract($limited, 'harmonies.create', 429);
        $this->assertGreaterThan(0, $limited->json('retryAfter'));

        $private = Harmony::query()->where('visibility', 'private')->firstOrFail();
        $refused = $this->patchJson('/api/harmonies/'.$private->public_id, ['visibility' => 'unlisted']);
        $this->assertMatchesContract($refused, 'harmonies.update', 429);

        app(Flags::class)->set('pause_sharing', 'on', null, 'Spam wave');
        $paused = $this->startHarmony($project, $wav);
        $this->assertMatchesContract($paused, 'harmonies.create', 423);
        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$private->public_id, ['visibility' => 'unlisted']), 'harmonies.update', 423);
        // Existing links keep working, and private shares still go up.
        $this->startHarmony($project, $wav, ['visibility' => 'private'])->assertCreated();

        app(Flags::class)->set('pause_sharing', 'off', null, 'Over');
        $unverified = User::factory()->withPassword()->unverified()->create()->fresh();
        $this->actingAs($unverified);
        $this->assertMatchesContract($this->startHarmony($project, $wav), 'harmonies.create', 403);
        $theirs = Harmony::factory()->private()->create(['project_id' => null, 'user_id' => $unverified?->id]);
        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$theirs->public_id, ['visibility' => 'unlisted']), 'harmonies.update', 403);
    }

    public function test_going_private_takes_the_files_off_the_cdn_and_coming_back_gives_new_addresses(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        $first = (string) $harmony->media_audio_key;

        $private = $this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'private', 'title' => 'For Gran, again']);
        $this->assertMatchesContract($private, 'harmonies.update', 200);
        $private->assertJsonPath('data.visibility', 'private')->assertJsonPath('data.title', 'For Gran, again');
        Storage::disk('media')->assertMissing($first);
        $this->assertNotNull($private->json('data.audioUrl'));

        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'unlisted'])->assertOk();
        $harmony->refresh();
        $this->assertNotSame($first, $harmony->media_audio_key);
        Storage::disk('media')->assertExists((string) $harmony->media_audio_key);

        $bad = $this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'public', 'title' => 'see www.example.org']);
        $this->assertMatchesContract($bad, 'harmonies.update', 422);
    }

    public function test_a_moderated_harmony_cant_be_shared_again_but_can_be_deleted(): void
    {
        $user = $this->owner();
        $harmony = Harmony::factory()->private()->underReview()->create(['project_id' => null, 'user_id' => $user->id]);

        $refused = $this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'unlisted']);
        $this->assertMatchesContract($refused, 'harmonies.update', 409);
        $this->getJson('/api/harmonies/'.$harmony->public_id)->assertJsonPath('data.moderation', 'under_review');

        $this->assertMatchesContract($this->deleteJson('/api/harmonies/'.$harmony->public_id), 'harmonies.delete', 204);
        $this->assertNotNull($harmony->fresh()?->deleted_at);
    }

    public function test_deleting_takes_the_page_files_and_videos_at_once_and_someone_elses_is_not_found(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        $video = ShareVideo::factory()->ready()->create(['harmony_id' => $harmony->id]);
        Storage::disk('private')->put((string) $video->file_key, 'mp4');
        $keys = [(string) $harmony->audio_key, (string) $harmony->card_key, (string) $video->file_key];
        $media = (string) $harmony->media_audio_key;

        $this->deleteJson('/api/harmonies/'.$harmony->public_id)->assertNoContent();
        $this->deleteJson('/api/harmonies/'.$harmony->public_id)->assertNoContent();

        foreach ($keys as $key) {
            Storage::disk('private')->assertMissing($key);
        }
        Storage::disk('media')->assertMissing($media);
        $this->assertSame(0, ShareVideo::query()->count());
        $this->getJson('/api/harmonies/'.$harmony->public_id)->assertNotFound();
        $this->getJson('/api/harmonies')->assertJsonCount(0, 'data');

        $other = Harmony::factory()->create();
        $hidden = $this->getJson('/api/harmonies/'.$other->public_id);
        $this->assertMatchesContract($hidden, 'harmonies.show', 404);
        $this->patchJson('/api/harmonies/'.$other->public_id, ['title' => 'x'])->assertNotFound();
        $this->deleteJson('/api/harmonies/'.$other->public_id)->assertNoContent();
        $this->assertNull($other->fresh()?->deleted_at);
    }

    public function test_a_file_that_isnt_the_wav_it_claims_fails_for_good(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $wav = Wav::stereo(1.0);
        $mono = Wav::tone(2.0);
        // Declared stereo, sent as mono of the same size.
        $id = (string) $this->startHarmony($project, $wav, ['sha256' => hash('sha256', $mono)])->json('data.id');
        $harmony = Harmony::query()->where('public_id', $id)->firstOrFail();
        Storage::disk('private')->put($harmony->upload_key, $mono);

        $this->postJson('/api/harmonies/'.$id.'/complete')->assertOk();

        $this->getJson('/api/harmonies/'.$id)->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure', 'wrong_format')->assertJsonPath('data.audioUrl', null);
    }

    public function test_a_mix_counts_toward_storage_and_waits_while_uploads_are_paused(): void
    {
        $user = $this->owner();
        [$project] = $this->recordedProject();
        $wav = Wav::stereo(1.0);
        $before = app(Plans::class)->storageUsed($user);
        $harmony = $this->shareHarmony($project, $wav);
        $this->assertSame($before + (int) $harmony->stored_bytes, app(Plans::class)->storageUsed($user));

        config(['usage.plans.free.storage_bytes' => app(Plans::class)->storageUsed($user) + 10]);
        $full = $this->startHarmony($project, $wav);
        $this->assertMatchesContract($full, 'harmonies.create', 403);
        $full->assertJsonPath('code', 'storage_limit');

        app(Flags::class)->set('pause_uploads', 'on', null, 'Storage attack');
        $this->startHarmony($project, $wav)->assertStatus(423);
    }

    public function test_a_failed_mix_cant_be_shared_and_a_new_title_makes_a_new_card(): void
    {
        $user = $this->owner();
        $failed = Harmony::factory()->private()->create(['project_id' => null, 'user_id' => $user->id, 'status' => Harmony::FAILED, 'failure' => 'wrong_format']);
        $refused = $this->patchJson('/api/harmonies/'.$failed->public_id, ['visibility' => 'unlisted']);
        $this->assertMatchesContract($refused, 'harmonies.update', 409);
        $refused->assertJsonPath('code', 'harmony_not_ready');

        [$project] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        [$card, $media] = [(string) $harmony->card_key, (string) $harmony->media_card_key];

        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['title' => 'For Gran, at Christmas'])->assertOk();

        $harmony->refresh();
        $this->assertNotSame($card, $harmony->card_key);
        Storage::disk('private')->assertMissing($card);
        Storage::disk('media')->assertMissing($media);
        Storage::disk('media')->assertExists((string) $harmony->media_card_key);

        // Renamed and shared again in one go: the page comes back with the new card.
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'private'])->assertOk();
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'unlisted', 'title' => 'For Gran, again'])->assertOk();
        Storage::disk('media')->assertExists((string) $harmony->fresh()?->media_audio_key);
    }

    public function test_a_part_or_owner_name_with_a_link_never_reaches_the_page(): void
    {
        $this->app->make(SyncOfficialTemplates::class)->handle();
        $user = $this->owner();
        $user->forceFill(['name' => 'Ana at mysite.com'])->save();
        [$project, $part] = $this->recordedProject();
        $this->savePart($part, $project, 2, ['name' => 'buy followers at scam.ly', 'currentTakeId' => Take::query()->value('uuid')])->assertOk();

        $harmony = $this->shareHarmony($project, Wav::tone(1.0), ['partId' => $part, 'tracks' => [['name' => 'Alto', 'color' => '#e4a94b', 'peaks' => base64_encode(str_repeat(chr(90), 20))]]]);

        $this->assertSame('Alto', $harmony->part_name);
        $facts = app(HarmonyFacts::class);
        $this->assertSame(__('sharing.a_singer'), $facts->singers($harmony->load('user'))[0]['name']);
    }

    public function test_a_try_after_the_files_were_saved_publishes_the_page(): void
    {
        $harmony = Harmony::factory()->create(['project_id' => null]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        $this->assertNull($harmony->media_audio_key);

        app()->call([new ProcessHarmony($harmony->id), 'handle']);

        Storage::disk('media')->assertExists((string) $harmony->fresh()?->media_audio_key);
    }

    public function test_signed_out_is_unauthenticated(): void
    {
        $this->postJson('/api/harmonies', [])->assertUnauthorized();
        $this->getJson('/api/harmonies')->assertUnauthorized();
    }
}
