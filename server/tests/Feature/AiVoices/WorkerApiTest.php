<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Enums\AiUploadStatus;
use App\Enums\GuideSource;
use App\Jobs\PublishAiGuide;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Services\Audio\Ffmpeg;
use App\Support\AiVoices\AiMark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The AI voices worker API (contract/api.md, "The AI voices worker"): its env-only token, what a song needs, and a
 * master sent, checked and published as the part's AI guide (real ffmpeg).
 */
final class WorkerApiTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai_worker.token' => self::TOKEN]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function worker(string $method, string $uri, array $body = [], string $token = self::TOKEN): TestResponse
    {
        return $this->json($method, $uri, $body, ['Authorization' => 'Bearer '.$token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function master(Template $template, AiVoice $voice, string $wav, string $part = 'tenor'): array
    {
        $frames = intdiv(strlen($wav) - 44, 2);

        return [
            'versionId' => $template->currentVersion?->sqid(), 'part' => $part, 'voice' => $voice->sqid(), 'sampleRate' => 48000,
            'frames' => $frames, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav),
            'checks' => ['pitchMaxCents' => 24.5, 'pitchMedianCents' => 8.1, 'notesMeasured' => 41, 'notesTotal' => 41, 'notesOver15' => 3, 'notesOver30' => 0, 'dropouts' => 2, 'heldSeconds' => 31.2, 'durationSeconds' => $frames / 48000],
            'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('a', 40), 'weightsRevision' => str_repeat('b', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'test-tenor'],
        ];
    }

    public function test_without_a_token_set_the_api_is_off_and_a_wrong_token_is_refused(): void
    {
        $this->catalogue();
        config(['services.ai_worker.token' => 'short']);
        $this->worker('GET', '/api/worker/ai/needs?template=amazing-grace', token: 'short')->assertNotFound()->assertJsonPath('code', 'not_found');

        config(['services.ai_worker.token' => self::TOKEN]);
        $this->worker('GET', '/api/worker/ai/needs?template=amazing-grace', token: 'wrong-token-wrong-token-wrong-token')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->getJson('/api/worker/ai/needs?template=amazing-grace')->assertUnauthorized();
        $this->getJson('/api/worker/ai/needs?template=amazing-grace&token='.self::TOKEN)->assertUnauthorized();
        // The token opens the worker routes only.
        $this->worker('GET', '/api/admin/ai-voices')->assertUnauthorized();
    }

    public function test_needs_lists_each_part_with_its_voices_and_the_notes(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create(['name' => 'Alto A']);
        AiVoice::factory()->withdrawn()->create();
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'bass']);
        $ai = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        $pending = AiUpload::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor', 'ai_voice_id' => $voice->id, 'status' => AiUploadStatus::Processing]);

        $needs = $this->worker('GET', '/api/worker/ai/needs?template=amazing-grace');
        $this->assertMatchesContract($needs, 'worker.ai.needs', 200);
        $parts = collect($needs->json('data.parts'))->keyBy('key');
        $this->assertSame($template->currentVersion?->sqid(), $needs->json('data.versionId'));
        $this->assertSame('amazing-grace', $needs->json('data.data.id'));
        $this->assertTrue($parts['bass']['hasRealGuide']);
        $this->assertFalse($parts['bass']['wanted']);
        $this->assertSame($ai->sqid(), $parts['alto']['aiGuide']['id']);
        $this->assertTrue($parts['alto']['wanted'], 'A better render may replace an AI guide.');
        $this->assertSame($pending->sqid(), $parts['tenor']['pending']['upload']);
        $this->assertSame([true, false], array_column($needs->json('meta.voices'), 'active'));

        $this->assertMatchesContract($this->worker('GET', '/api/worker/ai/needs?template=no-such-song'), 'worker.ai.needs', 404);
        $this->assertMatchesContract($this->worker('GET', '/api/worker/ai/needs'), 'worker.ai.needs', 422);
    }

    public function test_a_master_is_sent_checked_and_published_as_the_parts_ai_guide_replacing_the_last(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $earlier = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        Storage::disk('media')->put($earlier->media_key, 'aac');
        $wav = Wav::tone(1.5);

        $created = $this->worker('POST', '/api/worker/ai/uploads', $this->master($template, $voice, $wav));
        $this->assertMatchesContract($created, 'worker.ai.uploads.create', 201);
        $upload = AiUpload::query()->firstOrFail();
        $this->assertSame('uploading', $created->json('data.status'));

        $this->assertMatchesContract($this->worker('POST', "/api/worker/ai/uploads/{$upload->sqid()}/complete"), 'worker.ai.uploads.complete', 409);
        Storage::disk('private')->put($upload->upload_key, $wav);
        $completed = $this->worker('POST', "/api/worker/ai/uploads/{$upload->sqid()}/complete");
        $this->assertMatchesContract($completed, 'worker.ai.uploads.complete', 200);

        $shown = $this->worker('GET', "/api/worker/ai/uploads/{$upload->sqid()}");
        $this->assertMatchesContract($shown, 'worker.ai.uploads.show', 200);
        $shown->assertJsonPath('data.status', 'published');

        $guide = TemplateGuide::withAi()->where('part_key', 'tenor')->firstOrFail();
        $this->assertSame(GuideSource::Ai, $guide->source);
        $this->assertTrue($guide->is_default);
        $this->assertSame(0, $guide->offset_frames);
        $this->assertSame($guide->sqid(), $shown->json('data.guideId'));
        $probe = app(Ffmpeg::class)->probe(Storage::disk('media')->path($guide->media_key), upload: false);
        $this->assertSame('aac', $probe['codec'] ?? null);
        // The guide the app and the site play says it's AI-made, inside the file (EU AI Act Article 50).
        $tags = app(Ffmpeg::class)->tags(Storage::disk('media')->path($guide->media_key));
        $this->assertSame([config('ai_marking.text'), AiMark::line()], [$tags['comment'] ?? null, $tags['description'] ?? null]);
        $this->assertNull(TemplateGuide::withAi()->find($earlier->id), 'The earlier AI render is replaced.');
        Storage::disk('media')->assertMissing($earlier->media_key);
        Storage::disk('private')->assertMissing($upload->upload_key);

        $this->assertMatchesContract($this->worker('GET', '/api/worker/ai/uploads/zzzzzzzz'), 'worker.ai.uploads.show', 404);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/ai/uploads/zzzzzzzz/complete'), 'worker.ai.uploads.complete', 404);
    }

    public function test_a_file_that_isnt_what_was_declared_is_refused(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $wav = Wav::tone(1.0);
        $this->worker('POST', '/api/worker/ai/uploads', $this->master($template, $voice, $wav))->assertCreated();
        $upload = AiUpload::query()->firstOrFail();

        Storage::disk('private')->put($upload->upload_key, str_repeat("\0", strlen($wav)));
        $this->assertMatchesContract($this->worker('POST', "/api/worker/ai/uploads/{$upload->sqid()}/complete"), 'worker.ai.uploads.complete', 409);
        $this->assertSame('uploading', $upload->refresh()->status->value);

        // The right size and hash, but not the audio declared (a 44.1 kHz file): failed when it's checked.
        $other = Wav::tone(1.0, 44100);
        $master = $this->master($template, $voice, $other);
        $master['frames'] = intdiv(strlen($other) - 44, 2);
        $this->worker('POST', '/api/worker/ai/uploads', $master)->assertCreated();
        $second = AiUpload::query()->latest('id')->firstOrFail();
        Storage::disk('private')->put($second->upload_key, $other);
        $this->worker('POST', "/api/worker/ai/uploads/{$second->sqid()}/complete")->assertOk();
        $this->worker('GET', "/api/worker/ai/uploads/{$second->sqid()}")->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure', 'bad_audio');
        $this->assertSame(0, TemplateGuide::withAi()->count());
    }

    public function test_refusals_the_worker_can_act_on(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $wav = Wav::tone(0.5);

        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'bass']);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/ai/uploads', $this->master($template, $voice, $wav, 'bass')), 'worker.ai.uploads.create', 409);

        $master = $this->master($template, $voice, $wav);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/ai/uploads', ['part' => 'nope'] + $master), 'worker.ai.uploads.create', 404);
        $this->assertMatchesContract($this->worker('POST', '/api/worker/ai/uploads', ['bytes' => $master['bytes'] - 2] + $master), 'worker.ai.uploads.create', 422);
        // Its AI marking may follow the audio, up to 64 KB, never more.
        $this->worker('POST', '/api/worker/ai/uploads', ['bytes' => $master['bytes'] + 65538] + $master)->assertUnprocessable();
        // A few notes over 30 cents pass (2026-11-24): with 41 measured, up to 3 (5%, rounded up); 50 cents the worst.
        $this->worker('POST', '/api/worker/ai/uploads', ['checks' => ['notesOver30' => 4] + $master['checks']] + $master)->assertUnprocessable()->assertJsonValidationErrors('checks.notesOver30');
        $this->worker('POST', '/api/worker/ai/uploads', ['checks' => ['pitchMaxCents' => 50.5] + $master['checks']] + $master)->assertUnprocessable()->assertJsonValidationErrors('checks.pitchMaxCents');
        $this->worker('POST', '/api/worker/ai/uploads', ['checks' => ['pitchMedianCents' => 30.5] + $master['checks']] + $master)->assertUnprocessable()->assertJsonValidationErrors('checks.pitchMedianCents');
        // With few notes measured, 2 is still allowed and 3 isn't.
        $this->worker('POST', '/api/worker/ai/uploads', ['checks' => ['notesMeasured' => 10, 'notesOver30' => 3] + $master['checks']] + $master)->assertUnprocessable()->assertJsonValidationErrors('checks.notesOver30');
        $this->worker('POST', '/api/worker/ai/uploads', ['renderer' => ['reference' => '/home/x/ref.wav'] + $master['renderer']] + $master)->assertUnprocessable()->assertJsonValidationErrors('renderer.reference');
        $this->worker('POST', '/api/worker/ai/uploads', ['sampleRate' => 24000] + $master)->assertUnprocessable();

        $old = $template->currentVersion;
        $newer = $old?->replicate();
        $this->assertNotNull($newer);
        $newer->forceFill(['version' => (int) TemplateVersion::query()->where('template_id', $template->id)->max('version') + 1])->save();
        $template->forceFill(['current_version_id' => $newer->id])->save();
        $this->assertMatchesContract($this->worker('POST', '/api/worker/ai/uploads', $master), 'worker.ai.uploads.create', 409);
        $template->forceFill(['current_version_id' => $old->id])->save();

        $voice->forceFill(['withdrawn_at' => now()])->save();
        $this->assertMatchesContract($this->worker('POST', '/api/worker/ai/uploads', $master), 'worker.ai.uploads.create', 403);
    }

    public function test_a_real_voice_published_while_it_was_processing_wins(): void
    {
        Bus::fake([PublishAiGuide::class]);
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $wav = Wav::tone(0.5);
        $this->worker('POST', '/api/worker/ai/uploads', $this->master($template, $voice, $wav))->assertCreated();
        $upload = AiUpload::query()->firstOrFail();
        Storage::disk('private')->put($upload->upload_key, $wav);
        $this->worker('POST', "/api/worker/ai/uploads/{$upload->sqid()}/complete")->assertOk()->assertJsonPath('data.status', 'processing');
        Bus::assertDispatched(PublishAiGuide::class);

        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        (new PublishAiGuide($upload->id))->handle(app(Ffmpeg::class));

        $this->assertSame('real_voice_exists', $upload->refresh()->failure);
        $this->assertSame(0, TemplateGuide::withAi()->where('source', 'ai')->count());
    }

    public function test_one_slightly_off_note_no_longer_fails_a_part(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $master = $this->master($template, $voice, Wav::tone(0.5));

        $sent = $this->worker('POST', '/api/worker/ai/uploads', ['checks' => ['pitchMaxCents' => 46.0, 'notesOver30' => 3] + $master['checks']] + $master);
        $this->assertMatchesContract($sent, 'worker.ai.uploads.create', 201);
    }
}
