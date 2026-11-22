<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Models\BetaSinger;
use App\Models\Harmony;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Support\AiVoices\AiLicence;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Hear yourself as a choir launched before the catalogue's AI voices (Hillel, 2026-11-21): with ai_voices off and
 * own_choir and own_choir_songs on, the whole path works, request to claim to finish to listening, Download and a
 * share, and nothing of track A shows (no catalogue AI guides, no AI licence for beta singers).
 */
final class TrackBFirstTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;
    use Sharing;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    public function test_the_choir_works_end_to_end_with_the_catalogues_ai_voices_off(): void
    {
        $this->catalogue();
        $flags = app(Flags::class);
        $flags->set('ai_voices', 'off', null, 'Track B first');
        $flags->set('own_choir', 'on', null, 'Track B first');
        $flags->set('own_choir_songs', 'on', null, 'Track B first');
        config(['services.ai_worker.choir_token' => self::TOKEN]);
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];
        $user = $this->owner();
        $user->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        $this->actingAs($user->fresh() ?? $user);
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        OwnChoirVoice::factory()->create(['user_id' => $user->id]);

        // What the app reads: the choir on, the catalogue's AI voices off.
        $this->getJson('/api/auth/config')->assertJsonPath('data.ownChoir', true)->assertJsonPath('data.ownChoirSongs', true)->assertJsonPath('data.aiVoices', false);
        $this->get('/about/ai-voices')->assertOk();

        // Asked for, claimed, rendered, finished.
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertCreated();
        $song = OwnChoirRequest::query()->sole();
        $claim = $this->json('POST', '/api/worker/choir/claim', [], $headers)->assertOk();
        $this->assertNotNull($claim->json('data.reference'));
        $token = (string) $claim->json('data.claim');
        $wav = Wav::tone(1.0);
        $frames = intdiv(strlen($wav) - 44, 2);

        foreach ($claim->json('data.parts') as $key) {
            $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/parts", ['claim' => $token, 'part' => $key, 'sampleRate' => 48000, 'frames' => $frames, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav),
                'checks' => ['pitchMaxCents' => 20.0, 'pitchMedianCents' => 8.0, 'notesMeasured' => 40, 'notesTotal' => 40, 'notesOver15' => 1, 'notesOver30' => 0, 'dropouts' => 0, 'heldSeconds' => 1.0, 'durationSeconds' => 1.0],
                'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('a', 40), 'weightsRevision' => str_repeat('b', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'own-clip']], $headers)->assertCreated();
            $part = OwnChoirPart::query()->where('part_key', $key)->sole();
            Storage::disk('private')->put($part->upload_key, $wav);
            $this->json('POST', "/api/worker/choir/parts/{$part->sqid()}/complete", [], $headers)->assertOk();
        }

        $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/extend", ['claim' => $token, 'gpuSeconds' => 30], $headers)->assertOk();
        $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/finish", ['failed' => null, 'claim' => $token, 'gpuSeconds' => 45], $headers)->assertOk()->assertJsonPath('data.status', 'ready');

        // Listened to (the light copy), downloaded at full quality with Plus.
        $show = $this->getJson('/api/own-choir')->assertOk()->assertJsonPath('data.requests.0.status', 'ready')->assertJsonPath('data.requests.0.canDownload', true);
        $parts = OwnChoirPart::query()->get();
        $this->assertTrue($parts->every(fn (OwnChoirPart $p): bool => $p->listen_key !== null && $p->media_key !== null));
        $this->assertStringContainsString((string) basename((string) $parts->first()?->listen_key), (string) collect($show->json('data.requests.0.parts'))->pluck('url')->implode(' '));
        $this->getJson("/api/own-choir/requests/{$song->sqid()}/download")->assertOk()->assertJsonCount($parts->count(), 'data.parts');

        // Shared: credited as AI voices made from the sharer's singing, with the About AI voices link.
        [$project] = $this->projectWithPart();
        $shared = $this->startHarmony($project, Wav::stereo(2.0), ['choirParts' => [$parts->first()?->sqid()]])->assertCreated();
        $harmony = Harmony::query()->where('public_id', (string) $shared->json('data.id'))->firstOrFail();
        $this->assertNotEmpty($harmony->own_voice_parts);
        // As its mix job leaves it: made, with its files.
        $harmony->forceFill(['status' => Harmony::READY, 'audio_key' => 'harmonies/track-b.m4a', 'card_key' => 'harmonies/track-b.jpg'])->save();
        Storage::disk('private')->put('harmonies/track-b.m4a', 'aac');
        Storage::disk('private')->put('harmonies/track-b.jpg', 'jpg');
        app(HarmonyFiles::class)->sync($harmony);
        $page = (string) $this->get('/h/'.$harmony->refresh()->public_id)->assertOk()->getContent();
        // One line under the title when every part is made from one person's singing (2026-11-23).
        $this->assertStringContainsString(e(__('sharing.page.ai_own', ['name' => (string) $user->name])), $page);
        $this->assertStringContainsString('href="/about/ai-voices"', $page);

        // Track A stays out of sight: no catalogue AI guides, no AI licence for a beta singer.
        $this->getJson('/api/templates/amazing-grace')->assertOk()->assertJsonMissing(['source' => 'ai']);
        $this->assertFalse(AiLicence::offered(BetaSinger::factory()->create(['user_id' => $user->id])));
    }
}
