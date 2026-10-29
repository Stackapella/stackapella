<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Part;
use App\Models\Score;
use App\Models\Take;
use App\Models\User;
use App\Support\Trust\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\Support\Wav;
use Tests\TestCase;

final class PartsAndTakesTest extends TestCase
{
    use CloudSave;
    use RefreshDatabase;

    public function test_a_part_needs_its_project_first_and_keeps_versions_like_a_project(): void
    {
        $this->owner();
        $project = (string) Str::uuid();
        $part = (string) Str::uuid();

        $this->assertMatchesContract($this->savePart($part, $project), 'parts.save', 409);
        $this->saveProject($project)->assertOk();

        $made = $this->savePart($part, $project);
        $this->assertMatchesContract($made, 'parts.save', 200);
        $made->assertJsonPath('data.version', 1)->assertJsonPath('data.projectId', $project)->assertJsonPath('data.createdAt', '2026-10-20T09:05:00Z');

        $this->savePart($part, $project, 1, ['volume' => 0.8])->assertOk();
        $stale = $this->savePart($part, $project, 1, ['volume' => 0.5]);
        $this->assertMatchesContract($stale, 'parts.save', 409);
        $stale->assertJsonPath('current.volume', 0.8);

        $bad = $this->savePart($part, $project, 2, ['color' => '#E4A94B', 'name' => str_repeat('x', 61), 'currentTakeId' => (string) Str::uuid()]);
        $this->assertMatchesContract($bad, 'parts.save', 422);
        $bad->assertJsonValidationErrors(['color', 'name']);
        $this->savePart($part, $project, 2, ['currentTakeId' => (string) Str::uuid()])->assertJsonValidationErrors('currentTakeId');
    }

    public function test_deleted_is_deleted(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();

        $this->assertMatchesContract($this->deleteJson('/api/parts/'.$part.'?version=4&changeId='.Str::uuid()), 'parts.delete', 409);
        $this->assertMatchesContract($this->deleteJson('/api/parts/'.$part.'?version=1&changeId='.Str::uuid()), 'parts.delete', 204);
        $this->deleteJson('/api/parts/'.$part.'?version=1&changeId='.Str::uuid())->assertNoContent();

        $this->savePart($part, $project, 1)->assertStatus(409)->assertJsonPath('code', 'part_gone');
        $this->savePart($part, $project)->assertStatus(409)->assertJsonPath('code', 'part_gone');
        $this->assertMatchesContract($this->startTake((string) Str::uuid(), $part, Wav::tone(0.5)), 'takes.create', 409);
        $this->startTake((string) Str::uuid(), (string) Str::uuid(), Wav::tone(0.5))->assertJsonPath('code', 'part_missing');
    }

    public function test_a_take_goes_up_by_presigned_put_and_becomes_flac_with_its_outline(): void
    {
        $user = $this->owner();
        [, $part] = $this->projectWithPart();
        $id = (string) Str::uuid();
        $wav = Wav::tone(1.0);

        $created = $this->startTake($id, $part, $wav);
        $this->assertMatchesContract($created, 'takes.create', 201);
        $created->assertJsonPath('data.status', 'uploading')->assertJsonPath('data.offsetFrames', -960)->assertJsonPath('upload.method', 'PUT');
        $this->assertSame(['pitchPercent' => 90], Score::query()->where('take_uuid', $id)->firstOrFail()->summary);

        // An outbox retry: the same take, a fresh upload. Other numbers under the same id: no.
        $this->startTake($id, $part, $wav)->assertCreated()->assertJsonPath('data.id', $id);
        $this->startTake($id, $part, Wav::tone(1.0, hz: 330))->assertStatus(422)->assertJsonValidationErrors('id');

        $this->assertMatchesContract($this->postJson('/api/takes/'.$id.'/complete'), 'takes.complete', 409);
        $take = Take::query()->where('uuid', $id)->firstOrFail();
        Storage::disk('private')->put($take->upload_key, Wav::tone(1.0, hz: 440));
        $mismatch = $this->postJson('/api/takes/'.$id.'/complete');
        $this->assertMatchesContract($mismatch, 'takes.complete', 409);
        $mismatch->assertJsonPath('code', 'upload_mismatch');

        Storage::disk('private')->put($take->upload_key, $wav);
        // Another complete checking it this moment: answered as it is, still uploading.
        $lock = Cache::lock('upload:'.$take->upload_key, 10);
        $lock->get();
        $this->postJson('/api/takes/'.$id.'/complete')->assertOk()->assertJsonPath('data.status', 'uploading');
        $lock->release();
        $this->assertMatchesContract($this->postJson('/api/takes/'.$id.'/complete'), 'takes.complete', 200);
        // What's processed is a copy no upload link points at; a PUT sent again changes nothing.
        $sent = $take->upload_key;
        $this->assertStringStartsWith('uploads/received/', (string) Take::query()->whereKey($take->id)->value('upload_key'));
        Storage::disk('private')->assertMissing($sent);

        $shown = $this->getJson('/api/takes/'.$id);
        $this->assertMatchesContract($shown, 'takes.show', 200);
        $shown->assertJsonPath('data.status', 'ready')->assertJsonPath('data.framesPerPeak', 512);
        $this->assertNotNull($shown->json('data.url'));
        $this->assertSame((int) ceil(48000 / 512), strlen((string) base64_decode((string) $shown->json('data.peaks8'), true)));

        $take->refresh();
        Storage::disk('private')->assertExists((string) $take->file_key);
        Storage::disk('private')->assertMissing($take->upload_key);
        $this->assertStringStartsWith('fLaC', (string) Storage::disk('private')->get((string) $take->file_key));
        $this->assertSame($take->stored_bytes, $this->getJson('/api/usage')->json('data.storage.usedBytes'));
        $this->assertSame($user->id, $take->user_id);
    }

    public function test_a_file_that_isnt_the_audio_it_claims_fails_for_good(): void
    {
        $this->owner();
        [, $part] = $this->projectWithPart();
        $id = (string) Str::uuid();
        $fake = str_repeat('x', 48044);
        $this->postJson('/api/takes', ['id' => $id, 'partId' => $part, 'sampleRate' => 48000, 'frames' => 24000, 'offsetFrames' => 0, 'bytes' => 48044, 'sha256' => hash('sha256', $fake)])->assertCreated();
        Storage::disk('private')->put(Take::query()->where('uuid', $id)->firstOrFail()->upload_key, $fake);

        $this->postJson('/api/takes/'.$id.'/complete')->assertOk();

        $this->getJson('/api/takes/'.$id)->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure', 'not_audio')->assertJsonPath('data.url', null);
    }

    public function test_a_replaced_take_is_kept_listed_used_again_deleted_and_restored(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $first = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $first->uuid, 'nudgeFrames' => 40])->assertJsonPath('data.currentTakeId', $first->uuid);

        // A second take replaces it: the first is kept (Hillel: "keep all takes so users can swap
        // to try"), never binned, and counts toward storage.
        $second = (string) Str::uuid();
        $wav = Wav::tone(1.0, hz: 330);
        $this->startTake($second, $part, $wav)->assertCreated();
        $this->savePart($part, $project, 2, ['currentTakeId' => $second])->assertOk();
        $take = Take::query()->where('uuid', $second)->firstOrFail();
        Storage::disk('private')->put($take->upload_key, $wav);
        $this->postJson('/api/takes/'.$second.'/complete')->assertOk();
        $this->assertNull($first->refresh()->binned_at);
        $this->assertSame([], $this->getJson('/api/bin')->json('data'));

        $list = $this->getJson('/api/parts/'.$part.'/takes');
        $this->assertMatchesContract($list, 'parts.takes.list', 200);
        $list->assertJsonPath('data.0.take.id', $second)->assertJsonPath('data.0.current', true)
            ->assertJsonPath('data.1.take.id', $first->uuid)->assertJsonPath('data.1.current', false)->assertJsonPath('data.1.recordedBy', null);
        $this->assertNotNull($list->json('data.1.take.url'));
        $this->assertMatchesContract($this->getJson('/api/parts/'.Str::uuid().'/takes'), 'parts.takes.list', 404);

        // Back to the first, whole: the nudge and trims start again.
        $used = $this->postJson('/api/parts/'.$part.'/takes/'.$first->uuid.'/use');
        $this->assertMatchesContract($used, 'parts.takes.use', 200);
        $used->assertJsonPath('data.currentTakeId', $first->uuid)->assertJsonPath('data.nudgeFrames', 0)->assertJsonPath('data.trimStartFrames', 0);
        $this->assertNull($take->refresh()->binned_at);
        $this->assertMatchesContract($this->postJson('/api/parts/'.$part.'/takes/'.Str::uuid().'/use'), 'parts.takes.use', 404);

        // The second goes to Recently deleted; the current one can't.
        $this->assertMatchesContract($this->deleteJson('/api/takes/'.$second), 'takes.delete', 204);
        $this->deleteJson('/api/takes/'.$second)->assertNoContent();
        $this->assertMatchesContract($this->deleteJson('/api/takes/'.$first->uuid), 'takes.delete', 409);
        $this->assertMatchesContract($this->deleteJson('/api/takes/'.Str::uuid()), 'takes.delete', 404);
        $this->getJson('/api/bin')->assertJsonPath('data.0.kind', 'take')->assertJsonPath('data.0.id', $second)->assertJsonPath('data.0.partName', 'Alto');
        $this->getJson('/api/parts/'.$part.'/takes')->assertJsonCount(1, 'data');

        // Undo across devices: back from the bin, and the take it replaces is kept.
        $undone = $this->postJson('/api/takes/'.$second.'/restore', ['nudgeFrames' => 120]);
        $this->assertMatchesContract($undone, 'takes.restore', 200);
        $undone->assertJsonPath('data.currentTakeId', $second)->assertJsonPath('data.nudgeFrames', 120);
        $this->assertNull($take->refresh()->binned_at);
        $this->assertNull($first->refresh()->binned_at);

        // Offline, the same swap is a part save naming the take.
        $this->savePart($part, $project, 5, ['currentTakeId' => $first->uuid])->assertJsonPath('data.currentTakeId', $first->uuid);
        $this->assertNull($take->refresh()->binned_at);
    }

    public function test_a_take_whose_part_is_gone_cant_come_back(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();

        $this->deleteJson('/api/parts/'.$part.'?version=2&changeId='.Str::uuid())->assertNoContent();

        $this->assertNotNull($take->refresh()->binned_at);
        $this->assertMatchesContract($this->postJson('/api/takes/'.$take->uuid.'/restore'), 'takes.restore', 409);
    }

    public function test_uploads_wait_for_a_verified_address_and_fit_the_storage_net_of_the_current_take(): void
    {
        $user = User::factory()->withPassword()->unverified()->create()->fresh() ?? throw new \LogicException('No user.');
        $this->actingAs($user);
        [$project, $part] = $this->projectWithPart();

        $this->assertMatchesContract($this->startTake((string) Str::uuid(), $part, Wav::tone(0.5)), 'takes.create', 403);

        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user->refresh());
        $take = $this->recordTake($part, seconds: 1.0);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();

        // At the allowance: re-recording the part fits, a second part doesn't.
        config(['usage.plans.free.storage_bytes' => (int) $take->stored_bytes + 1000]);
        $this->startTake((string) Str::uuid(), $part, Wav::tone(0.01))->assertCreated();
        $other = (string) Str::uuid();
        $this->savePart($other, $project)->assertOk();
        $full = $this->startTake((string) Str::uuid(), $other, Wav::tone(0.5));
        $this->assertMatchesContract($full, 'takes.create', 403);
        $full->assertJsonPath('code', 'storage_limit');
    }

    public function test_a_take_is_at_most_10_minutes_and_a_day_of_uploads_stops_at_its_bytes(): void
    {
        $this->actingAs($user = User::factory()->withPassword()->create()->fresh() ?? throw new \LogicException('No user.'));
        [, $part] = $this->projectWithPart();
        // A megabyte a day: a 6-second take (about 576 KB) fits, a second one doesn't.
        $twentyMinutes = 20 * 60 * 48000;
        $long = $this->startTake((string) Str::uuid(), $part, Wav::tone(0.1), ['frames' => $twentyMinutes, 'bytes' => $twentyMinutes * 2 + 44]);
        $this->assertMatchesContract($long, 'takes.create', 422);
        $long->assertJsonValidationErrors('frames');

        app(Limits::class)->override('upload-bytes', 'free_mb', 1, $user, 'Testing');

        $this->startTake((string) Str::uuid(), $part, Wav::tone(6.0))->assertCreated();
        $over = $this->startTake((string) Str::uuid(), $part, Wav::tone(6.0));
        $this->assertMatchesContract($over, 'takes.create', 429);
        $over->assertJsonPath('code', 'rate_limited');
        $this->assertNotNull($over->headers->get('Retry-After'));
    }

    public function test_someone_elses_take_and_part_are_not_found(): void
    {
        $theirs = Take::factory()->create();
        $this->owner();

        $this->assertMatchesContract($this->getJson('/api/takes/'.$theirs->uuid), 'takes.show', 404);
        $this->postJson('/api/takes/'.$theirs->uuid.'/complete')->assertNotFound();
        $this->postJson('/api/takes/'.$theirs->uuid.'/restore')->assertNotFound();
        $part = Part::query()->findOrFail($theirs->part_id);
        $this->startTake((string) Str::uuid(), $part->uuid, Wav::tone(0.2))->assertNotFound();
        $this->deleteJson('/api/parts/'.$part->uuid.'?version=1&changeId='.Str::uuid())->assertNoContent();
        $this->assertTrue(Part::query()->whereKey($part->id)->exists());
    }

    public function test_a_parts_kind_is_kept_when_an_older_app_leaves_it_out(): void
    {
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException('No user.'));
        [$project, $part] = $this->projectWithPart();
        $this->savePart($part, $project, 1, ['kind' => 'instrument'])->assertOk()->assertJsonPath('data.kind', 'instrument');
        $this->savePart($part, $project, 2)->assertOk()->assertJsonPath('data.kind', 'instrument');
        $this->savePart($part, $project, 3, ['kind' => 'trumpet'])->assertStatus(422);

        $voice = (string) Str::uuid();
        $this->savePart($voice, $project)->assertOk()->assertJsonPath('data.kind', 'voice');
    }
}
