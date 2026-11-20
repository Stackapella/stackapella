<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Enums\AiUploadStatus;
use App\Jobs\BuildExport;
use App\Jobs\PublishAiGuide;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\BetaSinger;
use App\Models\DataExport;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The AI voice licence, agreed in the app (docs/ai-voices-build-plan.md, phase 1b): offered by an admin, accepted with
 * a credit choice, changed, withdrawn; a new version of the terms pauses new parts in that voice.
 */
final class AiLicenceTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    private ?User $admin = null;

    private function offered(): BetaSinger
    {
        $this->catalogue();
        [$singer] = $this->singerWithAlto();
        $this->admin = $this->actingAsAdmin();
        $offer = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/ai-licence");
        $this->assertMatchesContract($offer, 'admin.beta.singers.aiLicence.offer', 200);
        $this->assertNotNull($offer->json('data.aiLicence.offeredAt'));
        $this->asSinger($singer);

        return $singer;
    }

    public function test_not_offered_the_app_shows_nothing_and_accepting_is_refused(): void
    {
        $this->catalogue();
        [$singer] = $this->singerWithAlto();
        $this->asSinger($singer);

        $show = $this->getJson('/api/beta/ai-licence');
        $this->assertMatchesContract($show, 'beta.aiLicence.show', 200);
        $show->assertJsonPath('data.offered', false)->assertJsonPath('data.accepted', null);

        $this->assertMatchesContract($this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true]), 'beta.aiLicence.accept', 403);
        $this->assertSame(0, AiVoice::query()->count());

        $this->actingAsAdmin();
        $this->assertMatchesContract($this->postJson('/api/admin/beta/singers/zzzzzzzz/ai-licence'), 'admin.beta.singers.aiLicence.offer', 404);
    }

    public function test_offered_a_singer_reads_the_terms_and_accepts_with_their_credit_choice(): void
    {
        $singer = $this->offered();

        $terms = $this->getJson('/api/beta/agreement?for=ai_voice');
        $this->assertMatchesContract($terms, 'beta.agreement.show', 200);
        $terms->assertJsonPath('data.version', 1)->assertJsonPath('data.reviewed', false);
        $this->getJson('/api/beta/agreement?for=ai_voice&lang=es')->assertJsonPath('data.language', 'en');

        $this->getJson('/api/beta/ai-licence')->assertJsonPath('data.offered', true)->assertJsonPath('data.version', 1);
        $this->assertMatchesContract($this->postJson('/api/beta/ai-licence', ['version' => 1, 'credited' => true]), 'beta.aiLicence.accept', 422);
        $this->assertMatchesContract($this->postJson('/api/beta/ai-licence', ['version' => 0, 'adult' => true, 'credited' => true]), 'beta.aiLicence.accept', 409);

        $accepted = $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true]);
        $this->assertMatchesContract($accepted, 'beta.aiLicence.accept', 200);
        $accepted->assertJsonPath('data.accepted.version', 1)->assertJsonPath('data.accepted.credited', true);

        $voice = AiVoice::query()->sole();
        $this->assertSame($singer->id, $voice->beta_singer_id);
        $this->assertSame('Sarah K.', $voice->labelName(), 'Named by their account, as they chose.');

        $changed = $this->patchJson('/api/beta/ai-licence', ['credited' => false]);
        $this->assertMatchesContract($changed, 'beta.aiLicence.update', 200);
        $this->assertNull($voice->refresh()->labelName());

        // Admin sees who accepted which version, on the voice and on the singer.
        $this->actingAsAdmin($this->admin);
        $list = $this->getJson('/api/admin/ai-voices');
        $this->assertMatchesContract($list, 'admin.aiVoices.list', 200);
        $list->assertJsonPath('data.0.singer.id', $singer->sqid())->assertJsonPath('data.0.licence.version', 1);
        $this->getJson('/api/admin/beta/singers')->assertJsonPath('data.0.aiLicence.version', 1)->assertJsonPath('data.0.aiLicence.credited', false);
    }

    public function test_withdrawing_retires_what_the_voice_sang_and_accepting_again_makes_a_new_voice(): void
    {
        $this->offered();
        $this->assertMatchesContract($this->postJson('/api/beta/ai-licence/withdraw'), 'beta.aiLicence.withdraw', 409);
        $this->assertMatchesContract($this->patchJson('/api/beta/ai-licence', ['credited' => true]), 'beta.aiLicence.update', 409);
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertOk();
        $voice = AiVoice::query()->sole();
        $template = $this->catalogue();
        $guide = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        Storage::disk('media')->put($guide->media_key, 'aac');
        $this->getJson('/api/beta/ai-licence')->assertJsonPath('data.voice.guides.0.id', $guide->sqid());

        $withdrawn = $this->postJson('/api/beta/ai-licence/withdraw');
        $this->assertMatchesContract($withdrawn, 'beta.aiLicence.withdraw', 200);
        $withdrawn->assertJsonPath('meta.retired', 1)->assertJsonPath('data.accepted', null)->assertJsonPath('data.voice', null);
        $this->assertNotNull($withdrawn->json('data.withdrawnAt'));
        $this->assertNull(TemplateGuide::withAi()->find($guide->id));
        Storage::disk('media')->assertMissing($guide->media_key);

        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => false])->assertOk();
        $this->assertSame(2, AiVoice::query()->count(), 'A voice coming back is a new voice.');
    }

    public function test_a_new_version_of_the_terms_pauses_new_parts_in_that_voice(): void
    {
        $this->offered();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertOk();
        $voice = AiVoice::query()->sole();
        // As if the terms moved on to a version they haven't accepted.
        $voice->forceFill(['consent_version' => '0'])->save();
        config(['services.ai_worker.token' => self::TOKEN]);
        $template = $this->catalogue();
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];

        $this->json('GET', '/api/worker/ai/needs?template=amazing-grace', [], $headers)->assertJsonPath('meta.voices.0.licensed', false);
        $refused = $this->json('POST', '/api/worker/ai/uploads', [
            'versionId' => $template->currentVersion?->sqid(), 'part' => 'tenor', 'voice' => $voice->sqid(), 'sampleRate' => 48000, 'frames' => 48000, 'bytes' => 44 + 96000, 'sha256' => str_repeat('a', 64),
            'checks' => AiUpload::factory()->make()->checks, 'renderer' => AiUpload::factory()->make()->renderer,
        ], $headers);
        $this->assertMatchesContract($refused, 'worker.ai.uploads.create', 403);
        $refused->assertJsonPath('code', 'licence_changed');
    }

    public function test_forgetting_the_singer_withdraws_their_ai_voice_and_drops_their_name(): void
    {
        $singer = $this->offered();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertOk();
        $this->actingAsAdmin($this->admin);

        $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/forget")->assertOk();

        $voice = AiVoice::query()->sole();
        $this->assertNotNull($voice->withdrawn_at);
        $this->assertNull($voice->based_on);
        $this->assertNull($voice->labelName());
    }

    public function test_a_singer_whose_role_ended_can_still_change_or_withdraw_it_but_not_accept(): void
    {
        $singer = $this->offered();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertOk();
        $singer->forceFill(['revoked_at' => now()])->save();

        $this->getJson('/api/beta/parts')->assertForbidden()->assertJsonPath('code', 'singer_revoked');
        $this->getJson('/api/beta/ai-licence')->assertOk()->assertJsonPath('data.offered', false)->assertJsonPath('data.accepted.version', 1);
        $this->patchJson('/api/beta/ai-licence', ['credited' => false])->assertOk();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertForbidden()->assertJsonPath('code', 'not_offered');
        $this->postJson('/api/beta/ai-licence/withdraw')->assertOk();
        $this->postJson('/api/beta/ai-licence/withdraw')->assertStatus(409)->assertJsonPath('code', 'not_accepted');
    }

    public function test_the_command_never_overwrites_a_licence_accepted_in_the_app(): void
    {
        $this->offered();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => false])->assertOk();
        $voice = AiVoice::query()->sole();

        $this->artisan('app:ai-voice:consent', ['voice' => $voice->sqid(), '--date' => '2026-11-19', '--consent-version' => 'v9', '--credited' => true])->assertFailed();
        $this->assertSame('1', $voice->refresh()->consent_version);
        $this->assertFalse($voice->credited);
    }

    public function test_a_part_sent_before_the_terms_changed_is_not_published_after(): void
    {
        $this->offered();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertOk();
        $voice = AiVoice::query()->sole();
        $template = $this->catalogue();
        $wav = Wav::tone(0.5);
        $upload = AiUpload::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor', 'ai_voice_id' => $voice->id, 'status' => AiUploadStatus::Processing, 'master_key' => 'ai/masters/t.wav', 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav)]);
        Storage::disk('private')->put('ai/masters/t.wav', $wav);
        $voice->forceFill(['consent_version' => '0'])->save();

        (new PublishAiGuide($upload->id))->handle(app(Ffmpeg::class));

        $this->assertSame('licence_changed', $upload->refresh()->failure);
        $this->assertSame(0, TemplateGuide::withAi()->count());
    }

    public function test_the_licence_record_is_in_the_singers_own_export(): void
    {
        $singer = $this->offered();
        $this->postJson('/api/beta/ai-licence', ['version' => 1, 'adult' => true, 'credited' => true])->assertOk();
        $export = new DataExport(['status' => 'preparing']);
        $export->forceFill(['user_id' => $singer->user_id])->save();

        (new BuildExport($export->id))->handle();

        $zip = new \ZipArchive;
        $path = (string) tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, (string) Storage::disk('private')->get((string) $export->refresh()->file_key));
        $zip->open($path);
        $beta = (array) json_decode((string) $zip->getFromName('beta-singer.json'), true);
        $zip->close();
        @unlink($path);

        $this->assertSame(1, $beta['aiVoiceLicence'][0]['version'] ?? null);
        $this->assertTrue($beta['aiVoiceLicence'][0]['credited'] ?? null);
    }
}
