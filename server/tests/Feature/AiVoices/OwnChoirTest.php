<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Enums\OwnChoirStatus;
use App\Jobs\CheckChoirClip;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Support\Flags;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Hear yourself as a choir (track B phase 3, behind own_choir): consent, a clip recorded in the app, the renderer's
 * job, private parts, quotas, and keeping nothing (real ffmpeg).
 */
final class OwnChoirTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    private function singer(): User
    {
        $this->catalogue();
        $user = User::factory()->create();
        app(Flags::class)->set('own_choir', 'on', null, 'Testing');
        config(['services.ai_worker.choir_token' => self::TOKEN, 'own_choir.free_songs' => 5]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function clip(string $wav, string $song = 'amazing-grace'): array
    {
        return ['templateId' => $song, 'partKey' => 'alto', 'offsetFrames' => 24000, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function worker(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->json($method, $uri, $body, ['Authorization' => 'Bearer '.self::TOKEN]);
    }

    /**
     * @return array<string, mixed>
     */
    private function master(string $part, string $wav): array
    {
        $frames = intdiv(strlen($wav) - 44, 2);

        return ['part' => $part, 'sampleRate' => 48000, 'frames' => $frames, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav),
            'checks' => ['pitchMaxCents' => 20.0, 'pitchMedianCents' => 8.0, 'notesMeasured' => 40, 'notesTotal' => 40, 'notesOver15' => 1, 'notesOver30' => 0, 'dropouts' => 0, 'heldSeconds' => 1.0, 'durationSeconds' => $frames / 48000],
            'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('a', 40), 'weightsRevision' => str_repeat('b', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'own-clip']];
    }

    public function test_off_it_isnt_there(): void
    {
        $this->catalogue();
        $this->actingAs(User::factory()->create());

        $this->assertMatchesContract($this->getJson('/api/own-choir'), 'ownChoir.show', 200);
        $this->getJson('/api/own-choir')->assertOk()->assertExactJson(['data' => null]);
        $this->getJson('/api/auth/config')->assertJsonPath('data.ownChoir', false);
    }

    public function test_a_singer_hears_a_song_in_their_own_voice_and_the_clip_is_gone(): void
    {
        $user = $this->singer();
        $wav = Wav::tone(6.0);

        $this->assertMatchesContract($this->getJson('/api/own-choir'), 'ownChoir.show', 200);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests', $this->clip($wav)), 'ownChoir.requests.create', 403);
        $this->assertMatchesContract($this->postJson('/api/own-choir/consent', ['version' => OwnChoir::termsVersion()]), 'ownChoir.consent', 422);
        $this->assertMatchesContract($this->postJson('/api/own-choir/consent', ['version' => 9, 'adult' => true]), 'ownChoir.consent', 409);
        $this->assertMatchesContract($this->postJson('/api/own-choir/consent', ['version' => OwnChoir::termsVersion(), 'adult' => true]), 'ownChoir.consent', 200);

        $this->assertMatchesContract($this->postJson('/api/own-choir/requests', ['frames' => 48000 * 40] + $this->clip($wav)), 'ownChoir.requests.create', 422);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests', $this->clip($wav, 'no-such-song')), 'ownChoir.requests.create', 404);
        $created = $this->postJson('/api/own-choir/requests', $this->clip($wav));
        $this->assertMatchesContract($created, 'ownChoir.requests.create', 201);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests', $this->clip($wav)), 'ownChoir.requests.create', 409);
        $request = OwnChoirRequest::query()->sole();

        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$request->sqid()}/complete"), 'ownChoir.requests.complete', 409);
        Storage::disk('private')->put($request->upload_key, $wav);
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$request->sqid()}/complete"), 'ownChoir.requests.complete', 200);
        $this->assertNotNull($request->refresh()->clip_checked_at, 'The clip passed its check.');

        // The renderer claims it, sends every part, and finishes.
        $claim = $this->worker('POST', '/api/worker/choir/claim');
        $this->assertMatchesContract($claim, 'worker.choir.claim', 200);
        // The clip is always the tune: the melody, whatever part the app named (Hillel, 2026-11-23).
        $claim->assertJsonPath('data.clip.part', 'soprano')->assertJsonPath('data.clip.offsetFrames', 24000);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/choir/claim'), 'worker.choir.claim', 204);
        $token = (string) $claim->json('data.claim');
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$request->sqid()}/finish", ['failed' => null, 'claim' => $token]), 'worker.choir.jobs.finish', 409);
        $this->worker('POST', "/api/worker/choir/jobs/{$request->sqid()}/finish", ['failed' => 'render_failed', 'claim' => str_repeat('x', 32)])->assertStatus(409)->assertJsonPath('code', 'job_not_claimed');

        // The renderer sends the reference it prepared from the clip: it becomes their kept voice.
        $this->assertNull($claim->json('data.reference'));
        $reference = Wav::tone(2.0, 24000);
        $sent = $this->worker('POST', "/api/worker/choir/jobs/{$request->sqid()}/reference", ['claim' => $token, 'sampleRate' => 24000, 'frames' => intdiv(strlen($reference) - 44, 2), 'bytes' => strlen($reference), 'sha256' => hash('sha256', $reference), 'meta' => ['notes' => [[0, 1, 62]], 'words' => 'A-maz-ing']]);
        $this->assertMatchesContract($sent, 'worker.choir.references.create', 201);
        $voice = OwnChoirVoice::query()->sole();
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/references/{$voice->sqid()}/complete"), 'worker.choir.references.complete', 409);
        Storage::disk('private')->put($voice->upload_key, $reference);
        $done = $this->worker('POST', "/api/worker/choir/references/{$voice->sqid()}/complete");
        $this->assertMatchesContract($done, 'worker.choir.references.complete', 200);
        $done->assertJsonPath('data.status', 'ready');
        $this->assertMatchesContract($this->worker('POST', '/api/worker/choir/references/zzzzzzzz/complete'), 'worker.choir.references.complete', 404);

        $part = Wav::tone(1.0);
        foreach ($claim->json('data.parts') as $key) {
            $started = $this->worker('POST', "/api/worker/choir/jobs/{$request->sqid()}/parts", ['claim' => $token] + $this->master($key, $part));
            $this->assertMatchesContract($started, 'worker.choir.parts.create', 201);
            $row = OwnChoirPart::query()->where('part_key', $key)->sole();
            Storage::disk('private')->put($row->upload_key, $part);
            $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/parts/{$row->sqid()}/complete"), 'worker.choir.parts.complete', 200);
        }
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$request->sqid()}/parts", ['claim' => $token] + $this->master('nope', $part)), 'worker.choir.parts.create', 422);

        $clipKey = (string) $request->refresh()->clip_key;
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$request->sqid()}/finish", ['failed' => null, 'claim' => $token]), 'worker.choir.jobs.finish', 200);
        Storage::disk('private')->assertMissing($clipKey);
        $this->assertNull($request->refresh()->clip_key);

        $show = $this->getJson('/api/own-choir');
        $this->assertMatchesContract($show, 'ownChoir.show', 200);
        $show->assertJsonPath('data.requests.0.status', 'ready')->assertJsonPath('data.requests.0.seen', false)->assertJsonPath('data.requests.0.parts.0.source', 'ai');
        $this->assertCount(4, $show->json('data.requests.0.parts'));
        $media = OwnChoirPart::query()->first()?->media_key;
        $probe = app(Ffmpeg::class)->probe(Storage::disk('private')->path((string) $media), upload: false);
        $this->assertSame('aac', $probe['codec'] ?? null);
        // And a listening copy beside it, the one the app plays (full quality is Download's, with Plus).
        $listen = OwnChoirPart::query()->first()?->listen_key;
        $this->assertNotNull($listen);
        $this->assertSame('aac', app(Ffmpeg::class)->probe(Storage::disk('private')->path((string) $listen), upload: false)['codec'] ?? null);
        $this->assertNotSame($media, $listen);
        $this->assertStringContainsString(basename((string) $listen), (string) collect($show->json('data.requests.0.parts'))->pluck('url')->implode(' '));

        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$request->sqid()}/seen"), 'ownChoir.requests.seen', 200);
        $this->assertNotNull($show->json('data.voice.savedAt'));

        // The next song needs no new recording: made from their kept voice.
        $again = $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace']);
        $this->assertMatchesContract($again, 'ownChoir.requests.create', 201);
        $again->assertJsonPath('data.status', 'queued')->assertJsonPath('upload', null);
        $next = OwnChoirRequest::query()->latest('id')->firstOrFail();
        $job = $this->worker('POST', '/api/worker/choir/claim')->assertOk();
        $this->assertNull($job->json('data.clip'));
        $job->assertJsonPath('data.reference.id', $voice->sqid())->assertJsonPath('data.reference.meta.words', 'A-maz-ing');
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$next->sqid()}/reference", ['claim' => (string) $job->json('data.claim'), 'sampleRate' => 24000, 'frames' => 1, 'bytes' => 46, 'sha256' => str_repeat('a', 64), 'meta' => ['x' => 1]]), 'worker.choir.references.create', 409);

        // Deleting their voice: the song being made from it fails, the files go.
        $referenceKey = (string) $voice->refresh()->reference_key;
        $this->deleteJson('/api/own-choir/voice')->assertNoContent();
        Storage::disk('private')->assertMissing($referenceKey);
        $this->assertSame('reference_gone', $next->refresh()->failure);
        $this->assertMatchesContract($this->deleteJson('/api/own-choir/voice'), 'ownChoir.voice.delete', 404);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace']), 'ownChoir.requests.create', 409);

        // Someone else's song isn't theirs to see.
        $this->actingAs(User::factory()->create());
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$request->sqid()}/seen"), 'ownChoir.requests.seen', 404);
        $this->assertMatchesContract($this->deleteJson("/api/own-choir/requests/{$request->sqid()}"), 'ownChoir.requests.delete', 404);

        $this->actingAs($user);
        $this->assertMatchesContract($this->deleteJson("/api/own-choir/requests/{$request->sqid()}"), 'ownChoir.requests.delete', 204);
        Storage::disk('private')->assertMissing((string) $media);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests/zzzzzzzz/complete'), 'ownChoir.requests.complete', 404);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/choir/parts/zzzzzzzz/complete'), 'worker.choir.parts.complete', 404);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/choir/jobs/zzzzzzzz/finish', ['failed' => null, 'claim' => $token]), 'worker.choir.jobs.finish', 404);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/choir/jobs/zzzzzzzz/parts', ['claim' => $token] + $this->master('alto', $part)), 'worker.choir.parts.create', 404);
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$next->sqid()}/finish", ['failed' => 'nonsense', 'claim' => $token]), 'worker.choir.jobs.finish', 422);
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$next->sqid()}/reference", ['claim' => $token]), 'worker.choir.references.create', 422);
    }

    public function test_quotas_a_failed_song_gives_its_place_back_and_forget_takes_everything(): void
    {
        $user = $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        config(['own_choir.free_songs' => 1]);
        $wav = Wav::tone(6.0);
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $done = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready, 'tries' => 1]);

        $refused = $this->postJson('/api/own-choir/requests', $this->clip($wav));
        $this->assertMatchesContract($refused, 'ownChoir.requests.create', 403);
        $refused->assertJsonPath('code', 'quota_reached')->assertJsonPath('which', 'free');

        // Deleting the song doesn't give its place back; only a failed one does.
        $this->deleteJson("/api/own-choir/requests/{$done->sqid()}")->assertNoContent();
        $this->postJson('/api/own-choir/requests', $this->clip($wav))->assertForbidden()->assertJsonPath('which', 'free');
        $this->getJson('/api/own-choir')->assertJsonCount(0, 'data.requests');

        // Failed through ours (the renderer's own failure): given back.
        $done->forceFill(['status' => OwnChoirStatus::Failed, 'failure' => 'render_failed'])->save();
        $this->postJson('/api/own-choir/requests', $this->clip($wav))->assertCreated();

        $this->assertMatchesContract($this->deleteJson('/api/own-choir'), 'ownChoir.delete', 204);
        // Nothing of theirs is left but emptied rows that still count toward the quota.
        $this->assertSame(0, OwnChoirRequest::query()->where('status', '!=', 'deleted')->count());
        $this->assertSame(0, OwnChoirRequest::query()->whereNotNull('upload_key')->count());
        $this->assertSame(0, OwnChoirConsent::query()->count());
    }

    public function test_a_bad_clip_fails_and_a_job_whose_tries_run_out_is_dropped(): void
    {
        $user = $this->singer();
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $bad = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/bad.wav']);
        Storage::disk('private')->put('choir/clips/bad.wav', str_repeat("\0", 100));

        (new CheckChoirClip($bad->id))->handle(app(Ffmpeg::class));
        $this->assertSame('bad_clip', $bad->refresh()->failure);
        Storage::disk('private')->assertMissing('choir/clips/bad.wav');

        $stuck = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Rendering, 'clip_key' => 'choir/clips/stuck.wav', 'claimed_at' => now()->subHour(), 'tries' => 3]);
        Storage::disk('private')->put('choir/clips/stuck.wav', 'wav');
        $this->artisan('app:own-choir:prune')->assertSuccessful();
        $this->assertSame('render_failed', $stuck->refresh()->failure);
        Storage::disk('private')->assertMissing('choir/clips/stuck.wav');

        $claimed = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Rendering, 'claimed_at' => now()->subHour(), 'tries' => 1]);
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$claimed->sqid()}/finish", ['failed' => 'render_failed', 'claim' => str_repeat('a', 32)]), 'worker.choir.jobs.finish', 409);
        $this->assertMatchesContract($this->worker('POST', "/api/worker/choir/jobs/{$claimed->sqid()}/parts", ['claim' => str_repeat('a', 32)] + $this->master('alto', Wav::tone(0.5))), 'worker.choir.parts.create', 409);

        // A clip whose check never ran: failed after the hour, its clip gone, the singer free again.
        $lost = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/lost.wav']);
        Storage::disk('private')->put('choir/clips/lost.wav', 'wav');
        OwnChoirRequest::query()->whereKey($lost->id)->update(['updated_at' => now()->subHours(2)]);
        $this->artisan('app:own-choir:prune')->assertSuccessful();
        $this->assertSame('expired', $lost->refresh()->failure);
        Storage::disk('private')->assertMissing('choir/clips/lost.wav');
    }

    public function test_deleting_the_voice_mid_render_takes_the_clip_and_parts_with_it(): void
    {
        $user = $this->singer();
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $voice = OwnChoirVoice::factory()->create(['user_id' => $user->id, 'status' => 'uploading', 'reference_key' => null]);
        $request = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_token' => str_repeat('t', 32), 'clip_key' => 'choir/clips/mid.wav', 'own_choir_voice_id' => $voice->id]);
        Storage::disk('private')->put('choir/clips/mid.wav', 'wav');
        $part = OwnChoirPart::factory()->create(['own_choir_request_id' => $request->id]);
        Storage::disk('private')->put((string) $part->media_key, 'aac');

        $this->deleteJson('/api/own-choir/voice')->assertNoContent();

        $this->assertSame('reference_gone', $request->refresh()->failure);
        Storage::disk('private')->assertMissing('choir/clips/mid.wav');
        Storage::disk('private')->assertMissing((string) $part->media_key);
        $this->assertSame(0, OwnChoirPart::query()->count());
    }

    public function test_own_songs_from_the_creator_with_their_own_switch(): void
    {
        $user = $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        $data = (array) TemplateVersion::query()->whereKey(Template::query()->where('slug', 'amazing-grace')->value('current_version_id'))->value('data');
        $mine = Template::factory()->create(['owner_id' => $user->id, 'slug' => (string) Str::uuid()]);
        $version = TemplateVersion::factory()->create(['template_id' => $mine->id, 'data' => $data]);
        $mine->forceFill(['current_version_id' => $version->id])->save();
        $theirs = Template::factory()->create(['owner_id' => User::factory()->create()->id, 'slug' => (string) Str::uuid()]);
        $wav = Wav::tone(6.0);

        $this->postJson('/api/own-choir/requests', $this->clip($wav, (string) $mine->slug))->assertNotFound();
        $this->getJson('/api/auth/config')->assertJsonPath('data.ownChoirSongs', false);

        app(Flags::class)->set('own_choir_songs', 'on', null, 'Testing');
        $this->getJson('/api/auth/config')->assertJsonPath('data.ownChoirSongs', true);
        $this->postJson('/api/own-choir/requests', $this->clip($wav, (string) $theirs->slug))->assertNotFound();
        $this->postJson('/api/own-choir/requests', $this->clip($wav, (string) $mine->slug))->assertCreated()->assertJsonPath('data.templateId', $mine->slug);
    }

    public function test_the_catalogue_token_never_opens_the_choir_jobs(): void
    {
        $this->singer();
        config(['services.ai_worker.token' => str_repeat('c', 40)]);

        $this->json('POST', '/api/worker/choir/claim', [], ['Authorization' => 'Bearer '.str_repeat('c', 40)])->assertUnauthorized();
        $this->json('GET', '/api/worker/ai/needs?template=amazing-grace', [], ['Authorization' => 'Bearer '.self::TOKEN])->assertUnauthorized();
    }

    public function test_the_show_schema_is_the_state_or_null_and_never_drifts_from_it(): void
    {
        $read = static fn (string $name): array => (array) json_decode((string) file_get_contents(base_path('../contract/schemas/'.$name)), true);
        $state = $read('ownChoir.json')['$defs']['state'];
        $shown = $read('ownChoir.show.json')['properties']['data'];

        $this->assertSame(['object', 'null'], $shown['type']);
        unset($shown['type'], $shown['description'], $state['type'], $state['description']);
        $this->assertSame(json_encode($state, JSON_UNESCAPED_SLASHES), str_replace('ownChoir.json#/', '#/', (string) json_encode($shown, JSON_UNESCAPED_SLASHES)), 'ownChoir.show.json mirrors ownChoir.json\'s state: change both.');
    }

    public function test_only_plus_may_download_a_made_song_while_listening_stays_free(): void
    {
        $this->singer();
        $this->assertMatchesContract($this->getJson('/api/own-choir'), 'ownChoir.show', 200);
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $free = OwnChoirRequest::factory()->create(['user_id' => auth()->id(), 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.canDownload', false);
        $this->assertMatchesContract($this->postJson("/api/own-choir/requests/{$free->sqid()}/seen"), 'ownChoir.requests.seen', 200);
        $this->postJson("/api/own-choir/requests/{$free->sqid()}/seen")->assertJsonPath('data.canDownload', false);

        $plus = User::factory()->create(['plan' => 'plus']);
        $mine = OwnChoirRequest::factory()->create(['user_id' => $plus->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $this->actingAs($plus)->getJson('/api/own-choir')->assertJsonPath('data.requests.0.canDownload', true);
        $this->postJson("/api/own-choir/requests/{$mine->sqid()}/seen")->assertJsonPath('data.canDownload', true);
    }

    public function test_the_clip_is_always_the_tune_whatever_part_is_named(): void
    {
        $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', $this->clip($wav, 'silent-night') + ['partKey' => 'bass'])->assertCreated();

        $this->assertSame('soprano', OwnChoirRequest::query()->sole()->part_key, 'Silent Night\'s melody, not the bass of "oo"s.');
        $this->assertSame('soprano', OwnChoirRequests::melody(['parts' => [['key' => 'alto'], ['key' => 'soprano', 'melody' => true]]]));
        $this->assertSame('alto', OwnChoirRequests::melody(['parts' => [['key' => 'alto'], ['key' => 'tenor']]]), 'Unmarked: the first.');
    }
}
