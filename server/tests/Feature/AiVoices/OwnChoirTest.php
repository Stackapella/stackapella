<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Enums\OwnChoirStatus;
use App\Http\Resources\Worker\ChoirJobResource;
use App\Jobs\CheckChoirClip;
use App\Jobs\KeepVoiceClip;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Support\Contract\SchemaChecker;
use App\Support\Flags;
use App\Support\OwnChoir\ClipTune;
use App\Support\OwnChoir\OwnChoir;
use App\Support\OwnChoir\VoicingTransform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        // Free: the song's own tune (the free song is the song they sang); Plus tests choose others.
        return ['templateId' => $song, 'clipTemplateId' => in_array($song, ['silent-night', 'amazing-grace', 'joy-to-the-world'], true) ? $song : null, 'partKey' => 'alto', 'offsetFrames' => 24000, 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)];
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

        // The original clip is kept with their voice as AAC, for them to compare (Hillel, 2026-11-23).
        $kept = (string) $voice->refresh()->clip_key;
        $this->assertStringEndsWith('.m4a', $kept);
        Storage::disk('private')->assertExists($kept);
        $this->assertNotNull($this->getJson('/api/own-choir')->json('data.voice.clipListen.url'));

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
        // Both carry the AI marking (EU AI Act Article 50).
        foreach ([$media, $listen] as $file) {
            $tags = app(Ffmpeg::class)->tags(Storage::disk('private')->path((string) $file));
            $this->assertSame(['true', config('ai_marking.text'), config('ai_marking.generator')], [$tags['ai_generated'] ?? null, $tags['comment'] ?? null, $tags['ai_generator'] ?? null]);
        }
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

        $refused = $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav), ['clipTemplateId' => 'amazing-grace']));
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
        $user->forceFill(['store_plus_until' => now()->addDays(10)])->save(); // Plus: any song from any tune.
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
        $this->singer()->forceFill(['store_plus_until' => now()->addDays(10)])->save(); // Plus: any song from any tune.
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['partKey' => 'bass', 'clipTemplateId' => null]))->assertCreated();

        $this->assertSame('soprano', OwnChoirRequest::query()->sole()->part_key, 'Silent Night\'s melody, not the bass of "oo"s.');
        $this->assertSame('soprano', OwnChoirRequests::melody(['parts' => [['key' => 'alto'], ['key' => 'soprano', 'melody' => true]]]));
        $this->assertSame('alto', OwnChoirRequests::melody(['parts' => [['key' => 'alto'], ['key' => 'tenor']]]), 'Unmarked: the first.');
    }

    public function test_a_song_in_the_making_says_where_each_part_is(): void
    {
        $user = $this->singer();
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id'), 'status' => OwnChoirStatus::Queued]);
        $this->assertSame(['waiting'], array_values(array_unique(array_column(OwnChoir::progress($song), 'status'))));
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column(OwnChoir::progress($song), 'partKey'));

        $song->forceFill(['status' => OwnChoirStatus::Rendering])->save();
        OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => 'alto', 'status' => 'ready']);
        OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => 'tenor', 'status' => 'uploading']);
        $this->assertSame([['partKey' => 'soprano', 'status' => 'making'], ['partKey' => 'alto', 'status' => 'ready'], ['partKey' => 'tenor', 'status' => 'making'], ['partKey' => 'bass', 'status' => 'making']], OwnChoir::progress($song));

        // A friend's job: only their parts. Made: nothing.
        $song->forceFill(['only_parts' => ['alto', 'bass']])->save();
        $this->assertSame(['alto', 'bass'], array_column(OwnChoir::progress($song->fresh() ?? $song), 'partKey'));
        $song->forceFill(['status' => OwnChoirStatus::Ready])->save();
        $this->assertSame([], OwnChoir::progress($song));
        $this->getJson('/api/own-choir')->assertOk()->assertJsonPath('data.requests.0.progress', []);
    }

    public function test_the_clip_sings_the_hidden_voice_capture_tune_whatever_the_song(): void
    {
        $this->singer()->forceFill(['store_plus_until' => now()->addDays(10)])->save(); // Plus: any song from any tune.
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        // Only a tune from the allow-list (Hillel, 2026-11-23).
        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTemplateId' => 'happy-birthday']))->assertStatus(422)->assertJsonValidationErrors('clipTemplateId');

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['partKey' => 'bass', 'clipTemplateId' => null]))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $tune = $song->clipTemplateVersion ?? throw new \LogicException('The clip has its tune.');
        $this->assertSame('voice-capture', $tune->template?->slug);
        $this->assertSame(ClipTune::melody($tune), $song->part_key, 'Its melody, whatever part was named.');
        $this->assertSame('silent-night', $song->templateVersion?->template?->slug, 'The song made is still Silent Night.');

        // The worker gets the tune as written, apart from the song it renders.
        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $job = (new ChoirJobResource($song->fresh()))->resolve();
        $this->assertSame(['voice-capture', $tune->version], [$job['clip']['template'], $job['clip']['version']]);
        $this->assertSame((array) $tune->data, (array) $job['clip']['data']);
        $this->assertSame('silent-night', $job['template']);

        // Hidden: listed nowhere.
        $this->assertFalse(Template::query()->listed()->where('slug', 'voice-capture')->exists());
        $this->assertStringNotContainsString('voice-capture', (string) $this->getJson('/api/templates')->getContent());
    }

    public function test_a_singer_may_choose_another_tune_from_the_list(): void
    {
        $this->singer()->forceFill(['store_plus_until' => now()->addDays(10)])->save(); // Plus: any song from any tune.
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTemplateId' => 'joy-to-the-world']))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame('joy-to-the-world', $song->clipTemplateVersion?->template?->slug);
        $this->assertSame(ClipTune::melody($song->clipTemplateVersion), $song->part_key);

        // The list, in order, with where each stops; the worker gets only the notes sung, each unchanged.
        $this->getJson('/api/own-choir')->assertJsonPath('data.clipSongs.0', ['templateId' => 'voice-capture', 'stopBeat' => null, 'songId' => 'happy-birthday'])->assertJsonPath('data.clipSongs.3.templateId', 'joy-to-the-world');
        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $data = (new ChoirJobResource($song->fresh()))->resolve()['clip']['data'];
        $sung = collect((array) $data->parts)->firstWhere('key', $song->part_key)['guide']['notes'];
        $all = collect((array) $song->clipTemplateVersion?->data['parts'])->firstWhere('key', $song->part_key)['guide']['notes'];
        $this->assertNotEmpty($sung);
        $this->assertLessThan(count($all), count($sung));
        $this->assertSame(array_values(array_filter($all, static fn (array $n): bool => $n['beat'] < 63)), $sung);
    }

    public function test_a_singer_may_sing_the_songs_own_tune_once_through(): void
    {
        $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTemplateId' => 'silent-night']))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame($song->template_version_id, $song->clip_template_version_id);
        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $clip = (new ChoirJobResource($song->fresh()))->resolve()['clip'];
        $this->assertSame('silent-night', $clip['template']);
        $this->assertEquals(ClipTune::sung($song->templateVersion ?? throw new \LogicException), (array) $clip['data'], 'Its own tune, to its stop beat.');
    }

    public function test_a_low_voice_gets_the_low_voice_version(): void
    {
        // Hillel's first song: he sang the tune an octave down, his voice about 52 to 62.
        $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTemplateId' => 'silent-night', 'octave' => -12]))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame(['low', -12], [$song->voicing, $song->octave]);
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.voicing', 'low');

        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $job = (new ChoirJobResource($song->fresh()))->resolve();
        $this->assertSame('low', $job['voicing']);
        $shifts = array_column($job['shifts'], 'shift', 'partKey');
        $this->assertSame([-12, 0], [$shifts['soprano'], $shifts['bass']]);
        $written = collect((array) $song->templateVersion?->data['parts'])->firstWhere('key', 'soprano')['guide']['notes'][0]['pitch'];
        $this->assertSame($written - 12, collect((array) $job['data']->parts)->firstWhere('key', 'soprano')['guide']['notes'][0]['pitch']);
    }

    public function test_a_voice_that_suits_the_tune_as_written_keeps_it(): void
    {
        $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTemplateId' => 'silent-night', 'octave' => 0]))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame('written', $song->voicing);
        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $job = (new ChoirJobResource($song->fresh()))->resolve();
        $this->assertSame((array) $song->templateVersion?->data, (array) $job['data']);
        $this->assertSame([0], array_values(array_unique(array_column($job['shifts'], 'shift'))));
    }

    public function test_the_tune_is_moved_to_the_singers_first_note_and_the_worker_gets_it_moved(): void
    {
        $this->singer()->forceFill(['store_plus_until' => now()->addDays(10)])->save(); // Plus: any song from any tune.
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', $this->clip($wav, 'silent-night') + ['clipTranspose' => 13])->assertStatus(422)->assertJsonValidationErrors('clipTranspose');
        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTranspose' => -3, 'clipTemplateId' => 'voice-capture']))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame(-3, $song->clip_transpose);

        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $clip = (new ChoirJobResource($song->fresh()))->resolve()['clip'];
        $this->assertSame(-3, $clip['transpose']);
        $written = collect((array) $song->clipTemplateVersion?->data['parts'])->firstWhere('key', $song->part_key)['guide']['notes'];
        $sung = collect((array) $clip['data']->parts)->firstWhere('key', $song->part_key)['guide']['notes'];
        $this->assertSame(array_map(static fn (array $n): int => (int) $n['pitch'] - 3, array_filter($written, static fn (array $n): bool => isset($n['pitch']))), array_map(static fn (array $n): int => (int) $n['pitch'], array_filter($sung, static fn (array $n): bool => isset($n['pitch']))));
    }

    public function test_the_low_voice_tune_keeps_its_shape_and_inner_parts_stay_above_the_bass(): void
    {
        // Ma'oz Tzur (Hillel heard its second note jump up): the tune moves down whole, every interval as written.
        $this->catalogue();
        $notes = static fn (array $d, string $key): array => array_values(array_filter(collect((array) $d['parts'])->firstWhere('key', $key)['guide']['notes'], static fn (array $n): bool => isset($n['pitch'])));

        foreach (['maoz-tzur', 'silent-night'] as $slug) {
            $data = (array) Template::query()->where('slug', $slug)->firstOrFail()->currentVersion?->data;

            foreach ([VoicingTransform::low($data)['data'], VoicingTransform::full($data)['data']] as $i => $made) {
                $tune = $notes($made, $i === 0 ? 'soprano' : 'soprano-low');
                $this->assertSame(array_map(static fn (array $n): int => $n['pitch'] - 12, $notes($data, 'soprano')), array_column($tune, 'pitch'), "{$slug}: the tune an octave down, whole.");
                $bass = $notes($made, 'bass');
                $alto = $notes($made, $i === 0 ? 'alto' : 'alto-low');

                foreach ($alto as $j => $n) {
                    $written = $notes($data, 'alto')[$j]['pitch'];
                    $this->assertContains($n['pitch'], [$written, $written - 12]);
                    foreach (array_filter($bass, static fn (array $b): bool => $b['beat'] < $n['beat'] + max((float) $n['length'], 0.001) && $n['beat'] < $b['beat'] + $b['length']) as $b) {
                        $this->assertGreaterThanOrEqual($b['pitch'], $n['pitch'], "{$slug}: the alto stays above the bass");
                    }
                }
            }
        }
    }

    public function test_the_kept_clip_goes_with_the_voice_and_is_never_anyone_elses(): void
    {
        $owner = $this->singer();
        $voice = OwnChoirVoice::factory()->create(['user_id' => $owner->id, 'status' => 'ready', 'clip_key' => 'choir/voices/kept.m4a', 'saved_at' => now()]);
        Storage::disk('private')->put('choir/voices/kept.m4a', 'aac');
        $this->getJson('/api/own-choir')->assertJsonPath('data.voice.clipListen.expiresAt', fn (mixed $at): bool => is_string($at));

        // Someone else never sees it.
        $this->actingAs(User::factory()->create());
        $this->assertNull($this->getJson('/api/own-choir')->json('data.voice'));

        // Stopping (their voices dropped) deletes it.
        OwnChoirRequests::dropVoices($owner);
        Storage::disk('private')->assertMissing('choir/voices/kept.m4a');
        $this->assertNull(OwnChoirVoice::query()->find($voice->id));
    }

    public function test_a_voice_deleted_before_its_clip_is_kept_keeps_nothing(): void
    {
        $owner = $this->singer();
        $voice = OwnChoirVoice::factory()->create(['user_id' => $owner->id, 'status' => 'ready']);
        Storage::disk('private')->put('choir/clips/late.wav', Wav::tone(6.0));
        $voice->delete();

        (new KeepVoiceClip($voice->id, 'choir/clips/late.wav'))->handle(app(Ffmpeg::class));

        Storage::disk('private')->assertMissing('choir/clips/late.wav');
        $this->assertSame([], array_values(array_filter(Storage::disk('private')->allFiles('choir/voices'))));
    }

    public function test_a_clip_sung_with_no_headphones_says_so_to_the_worker(): void
    {
        $this->singer()->forceFill(['store_plus_until' => now()->addDays(10)])->save(); // Plus: any song from any tune.
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $this->postJson('/api/own-choir/requests', $this->clip($wav, 'silent-night') + ['guide' => false])->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $this->assertFalse($song->guide);
        $song->forceFill(['status' => OwnChoirStatus::Queued, 'clip_key' => 'choir/clips/x.wav'])->save();
        $this->assertFalse((new ChoirJobResource($song->fresh()))->resolve()['clip']['guide']);

        // Absent: with headphones, as before.
        $song->forceFill(['guide' => true])->save();
        $this->assertTrue((new ChoirJobResource($song->fresh()))->resolve()['clip']['guide']);
    }

    public function test_the_free_song_is_the_song_they_sang(): void
    {
        $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => auth()->id()]);
        $wav = Wav::tone(8.0);

        $refused = $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'silent-night'), ['clipTemplateId' => 'voice-capture']));
        $this->assertMatchesContract($refused, 'ownChoir.requests.create', 409);
        $refused->assertJsonPath('code', 'free_song_is_your_clip')->assertJsonPath('songId', 'happy-birthday');

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'happy-birthday'), ['clipTemplateId' => 'voice-capture']))->assertCreated();
        $this->assertSame('happy-birthday', OwnChoirRequest::query()->sole()->templateVersion?->template?->slug);
    }

    public function test_an_unusable_clip_says_why_to_its_singer_and_the_day_counts_it(): void
    {
        config(['counts.enabled' => true]);
        $user = $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        $wav = Wav::tone(8.0);
        $this->postJson('/api/own-choir/requests', $this->clip($wav, 'silent-night'))->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        Storage::disk('private')->put($song->upload_key, $wav);
        $this->postJson("/api/own-choir/requests/{$song->sqid()}/complete")->assertOk();
        $claim = $this->worker('POST', '/api/worker/choir/claim')->assertOk();

        $this->worker('POST', "/api/worker/choir/jobs/{$song->sqid()}/finish", ['failed' => 'clip_unusable', 'claim' => (string) $claim->json('data.claim'), 'detail' => 'octave'])->assertStatus(422);
        $this->worker('POST', "/api/worker/choir/jobs/{$song->sqid()}/finish", ['failed' => 'clip_unusable', 'claim' => (string) $claim->json('data.claim'), 'detail' => 'noisy', 'level' => 'quiet'])->assertOk();

        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.failureDetail', 'noisy');
        $counts = DB::table('usage_counts')->pluck('count', 'kind')->all();
        $this->assertSame([1, 1, 1, 1], [$counts['choir_clip_fail_silent'] ?? 0, $counts['choir_clip_noisy'] ?? 0, $counts['choir_take_level_quiet'] ?? 0, $counts['choir_guide_on'] ?? 0]);
        $this->assertSame(0, DB::table('usage_counts')->where('kind', 'like', '%'.$user->id.'%')->count(), 'Nothing about who.');
    }

    public function test_the_whole_song_sung_is_kept_as_its_lead_in_the_key_it_was_sung(): void
    {
        $user = $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        $clipWav = Wav::tone(8.0);
        $whole = Wav::tone(40.0);
        $lead = ['sampleRate' => 48000, 'frames' => intdiv(strlen($whole) - 44, 2), 'bytes' => strlen($whole), 'sha256' => hash('sha256', $whole), 'offsetFrames' => 24000];

        // Only with a clip of the song's own tune, never with no headphones.
        $this->postJson('/api/own-choir/requests', array_replace($this->clip($clipWav, 'silent-night'), ['lead' => $lead, 'guide' => false]))->assertStatus(422);
        $user->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        $this->postJson('/api/own-choir/requests', array_replace($this->clip($clipWav, 'silent-night'), ['lead' => $lead, 'clipTemplateId' => 'amazing-grace']))->assertStatus(422);

        $made = $this->postJson('/api/own-choir/requests', array_replace($this->clip($clipWav, 'silent-night'), ['lead' => $lead, 'clipTranspose' => -2]));
        $this->assertMatchesContract($made, 'ownChoir.requests.create', 201);
        $made->assertJsonPath('leadUpload.method', 'PUT')->assertJsonPath('data.canUseRecordingAsLead', true)->assertJsonPath('data.leadTake', null);
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame(-2, $song->key_shift);

        // Both go up before it's queued; then the lead is made, levelled, as AAC.
        Storage::disk('private')->put($song->upload_key, $clipWav);
        $this->postJson("/api/own-choir/requests/{$song->sqid()}/complete")->assertStatus(409)->assertJsonPath('code', 'upload_missing');
        Storage::disk('private')->put((string) $song->lead_upload_key, $whole);
        $this->postJson("/api/own-choir/requests/{$song->sqid()}/complete")->assertOk();
        $song->refresh();
        $this->assertNotNull($song->lead_key);
        $this->assertNull($song->lead_upload_key, 'The WAV goes once the lead is made.');
        $this->assertSame('aac', app(Ffmpeg::class)->probe(Storage::disk('private')->path((string) $song->lead_key), upload: false)['codec'] ?? null);
        $shown = $this->getJson('/api/own-choir');
        $this->assertMatchesContract($shown, 'ownChoir.show', 200);
        $shown->assertJsonPath('data.requests.0.leadTake.offsetFrames', 24000)->assertJsonPath('data.requests.0.leadTake.partKey', 'soprano');

        // Every part is rendered in the key it was sung, the tune included.
        $job = (new ChoirJobResource($song->fresh()))->resolve();
        $written = collect((array) $song->templateVersion?->data['parts'])->firstWhere('key', 'bass')['guide']['notes'][0]['pitch'];
        $this->assertSame($written - 2, collect((array) $job['data']->parts)->firstWhere('key', 'bass')['guide']['notes'][0]['pitch']);
        $this->assertCount(4, $job['parts']);

        // Their voice gone: the lead goes with it.
        OwnChoirRequests::dropVoices($user);
        Storage::disk('private')->assertMissing((string) $song->lead_key);
        $this->assertNull($song->refresh()->lead_key);
    }

    public function test_happy_birthdays_lead_is_the_capture_clips_first_section(): void
    {
        $user = $this->singer();
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        $wav = Wav::tone(30.0);

        $this->postJson('/api/own-choir/requests', array_replace($this->clip($wav, 'happy-birthday'), ['clipTemplateId' => 'voice-capture', 'clipTranspose' => 1]))->assertCreated()->assertJsonPath('leadUpload', null)->assertJsonPath('data.canUseRecordingAsLead', true);
        $song = OwnChoirRequest::query()->sole();
        $this->assertSame(-1, $song->key_shift, 'Its section is in F, the song in G, and the tune moved up 1.');
        Storage::disk('private')->put($song->upload_key, $wav);
        $this->postJson("/api/own-choir/requests/{$song->sqid()}/complete")->assertOk();

        $made = (array) $song->refresh()->lead['made'];
        $this->assertSame(24000 + (int) round((25 * 0.6 + 1) * 48000), $made['frames'], 'To its last beat and a second.');
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.leadTake.frames', $made['frames']);
    }

    public function test_vibrato_and_variation_replace_humanize_and_an_older_app_still_works(): void
    {
        $user = $this->singer();
        $user->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        OwnChoirVoice::factory()->create(['user_id' => $user->id, 'status' => 'ready']);
        $this->catalogue();
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id'), 'status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $base = ['voicing' => 'written', 'breaths' => 'legato', 'quality' => 'standard', 'ensembleTiming' => 'natural', 'pitchSpread' => 'off'];

        // Unset: natural and natural, humanize derived for older apps.
        $this->getJson('/api/own-choir')->assertJsonPath('data.requests.0.settings.sung.vibrato', 'natural')->assertJsonPath('data.requests.0.settings.sung.variation', 'natural')->assertJsonPath('data.requests.0.settings.sung.humanize', 2);

        $this->postJson("/api/own-choir/requests/{$song->sqid()}/remake", ['sung' => $base + ['vibrato' => 'wobbly', 'variation' => 'natural']])->assertStatus(422);
        $made = $this->postJson("/api/own-choir/requests/{$song->sqid()}/remake", ['sung' => $base + ['vibrato' => 'strong', 'variation' => 'loose']]);
        $this->assertMatchesContract($made, 'ownChoir.requests.remake', 202);
        $made->assertJsonPath('data.remake.settings.vibrato', 'strong')->assertJsonPath('data.remake.settings.humanize', 3);
        $remake = OwnChoirRequest::query()->where('remake', true)->sole();
        $settings = (new ChoirJobResource($remake->load('templateVersion.template')))->resolve()['settings'];
        $this->assertSame(['strong', 'loose'], [$settings->vibrato, $settings->variation]);

        // An older app sends humanize alone: read as both.
        $remake->forceFill(['status' => OwnChoirStatus::Deleted])->save();
        $this->postJson("/api/own-choir/requests/{$song->sqid()}/remake", ['sung' => $base + ['humanize' => 0]])->assertStatus(202)->assertJsonPath('data.remake.settings.vibrato', 'none')->assertJsonPath('data.remake.settings.variation', 'steady');
    }

    public function test_a_template_may_say_how_a_song_not_in_english_is_sung(): void
    {
        $checker = new SchemaChecker((string) config('templates.schemas'));
        $read = static fn (): object => json_decode((string) file_get_contents(base_path('database/templates/maoz-tzur.json')));

        $good = $read();
        $good->sungRespelling = [(object) ['syllable' => 'ma', 'sung' => 'mah'], (object) ['syllable' => 'tzur', 'sung' => 'tsoor']];
        $this->assertSame([], $checker->check($good, 'template.json'));

        $bad = $read();
        $bad->sungRespelling = [(object) ['syllable' => 'ma', 'sung' => 'mah', 'shown' => true]];
        $this->assertNotSame([], $checker->check($bad, 'template.json'));
    }
}
